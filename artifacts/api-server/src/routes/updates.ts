// ────────────────────────────────────────────────────────────────────────────
// Task #439 — License-gated auto-update server (Akeeba/RSJoomla model).
//
// Joomla periodically pulls the <updateservers> URL from each installed
// package's manifest. We host that endpoint here and gate it by license:
//   • pkg_aiboost (free)     → always returns the latest version.
//   • pkg_aiboost_pro        → requires a valid `key` (active, not expired,
//                              and the `domain` query param must appear in
//                              `licenses.activated_domains`). Anything else
//                              → returns an empty but valid <updates/> XML
//                              so Joomla silently shows "no updates".
//
// Every hit is logged to `update_check_log` (key_hash = SHA-256 of the
// license key — never the plain key) so we can answer questions like
// "how many sites are still on v0.42.0?" without storing PII.
// ────────────────────────────────────────────────────────────────────────────

import { Router, type IRouter } from "express";
import { createHash } from "node:crypto";
import { desc, eq } from "drizzle-orm";
import {
  licensesTable,
  pkgVersionsTable,
  updateCheckLogTable,
} from "@workspace/db/schema";
import { db } from "../lib/db";

const router: IRouter = Router();

type Verdict = "served" | "denied" | "empty";

const ALLOWED_SLUGS = new Set(["pkg_aiboost", "pkg_aiboost_pro"]);

function escapeXml(value: string): string {
  return value
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&apos;");
}

function emptyUpdatesXml(): string {
  return `<?xml version="1.0" encoding="utf-8"?>\n<updates></updates>\n`;
}

function buildUpdatesXml(
  slug: string,
  version: string,
  downloadUrl: string,
  description: string,
): string {
  const niceName =
    slug === "pkg_aiboost_pro"
      ? "AI Boost for Joomla — Pro Upgrade"
      : "AI Boost for Joomla";
  return [
    `<?xml version="1.0" encoding="utf-8"?>`,
    `<updates>`,
    `  <update>`,
    `    <name>${escapeXml(niceName)}</name>`,
    `    <description>${escapeXml(description)}</description>`,
    `    <element>${escapeXml(slug)}</element>`,
    `    <type>package</type>`,
    `    <version>${escapeXml(version)}</version>`,
    `    <downloads>`,
    `      <downloadurl type="full" format="zip">${escapeXml(downloadUrl)}</downloadurl>`,
    `    </downloads>`,
    `    <targetplatform name="joomla" version="4|5|6"/>`,
    `  </update>`,
    `</updates>`,
    ``,
  ].join("\n");
}

function hashKey(key: string): string {
  return createHash("sha256").update(key).digest("hex");
}

router.get("/updates/:packageSlug.xml", async (req, res) => {
  const slug = String(req.params.packageSlug ?? "").trim();
  const key = typeof req.query.key === "string" ? req.query.key.trim() : "";
  const domain =
    typeof req.query.domain === "string" ? req.query.domain.trim() : "";
  const currentVersion =
    typeof req.query.v === "string" ? req.query.v.trim().slice(0, 32) : "";

  res.type("application/xml");

  if (!ALLOWED_SLUGS.has(slug)) {
    res.status(404).send(emptyUpdatesXml());
    return;
  }

  const isPro = slug === "pkg_aiboost_pro";
  let verdict: Verdict = "empty";

  try {
    if (isPro) {
      if (!key || !domain) {
        verdict = "denied";
      } else {
        const rows = await db
          .select()
          .from(licensesTable)
          .where(eq(licensesTable.licenseKey, key))
          .limit(1);
        const lic = rows[0];
        const now = new Date();
        const domainOk =
          lic?.activatedDomains?.some(
            (d) => d.toLowerCase() === domain.toLowerCase(),
          ) ?? false;
        const notExpired = !lic?.expiresAt || lic.expiresAt > now;
        if (lic && lic.status === "active" && notExpired && domainOk) {
          verdict = "served";
        } else {
          verdict = "denied";
        }
      }
    } else {
      verdict = "served";
    }

    let body = emptyUpdatesXml();
    if (verdict === "served") {
      const versions = await db
        .select()
        .from(pkgVersionsTable)
        .where(eq(pkgVersionsTable.packageSlug, slug))
        .orderBy(desc(pkgVersionsTable.releasedAt), desc(pkgVersionsTable.id))
        .limit(1);
      const latest = versions[0];
      if (latest) {
        body = buildUpdatesXml(
          slug,
          latest.version,
          latest.downloadUrl,
          latest.releaseNotes ?? "",
        );
      } else {
        verdict = "empty";
      }
    }

    try {
      await db.insert(updateCheckLogTable).values({
        keyHash: key ? hashKey(key) : null,
        domain: domain || null,
        currentVersion: currentVersion || null,
        packageSlug: slug,
        verdict,
      });
    } catch (logErr) {
      req.log.warn({ err: logErr }, "[updates] failed to log check");
    }

    req.log.info(
      { slug, verdict, currentVersion, domain: domain || null },
      "[updates] served XML",
    );
    res.status(200).send(body);
  } catch (err) {
    req.log.error({ err, slug }, "[updates] error serving update XML");
    res.status(200).send(emptyUpdatesXml());
  }
});

// Convenience alias so the same logic is reachable via
// /api/updates/pkg_aiboost_pro?key=...&domain=... (no .xml).
router.get("/updates/:packageSlug", (req, res, next) => {
  const slug = String(req.params.packageSlug ?? "");
  if (slug.endsWith(".xml")) {
    next();
    return;
  }
  req.url = req.url.replace(`/updates/${slug}`, `/updates/${slug}.xml`);
  next();
});

export default router;
