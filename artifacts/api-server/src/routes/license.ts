// ────────────────────────────────────────────────────────────────────────────
// MOCK — Replace with real Lemon Squeezy API call before production.
//
// Today this route fakes Lemon Squeezy responses so the Joomla plugin's
// License Key Verify flow can be developed and demoed end-to-end without an
// LS vendor account. Task #429 (Free/Pro split + mock License Key).
//
// Swap plan when LS account exists (~50 LOC, one file):
//   1. Replace `mockValidate()` body with a fetch to
//      POST https://api.lemonsqueezy.com/v1/licenses/validate
//      Headers: Authorization: Bearer ${process.env.LEMONSQUEEZY_API_KEY}
//      Body:    { license_key, instance_name: site_domain }
//   2. Map LS variant_id → SKU through a lookup table (set after Bojan
//      creates the LS products).
//   3. Replace `mockDeactivate()` body with POST /v1/licenses/deactivate.
//   4. Add `/license/webhook` (HMAC-SHA256 X-Signature verify) and persist
//      `order_created` / `license_key_created` events to a `licenses` table.
//
// Mock key conventions (anything else returns invalid):
//   AB-VALID-*    → active, +1y expiry, 1 activation remaining
//   AB-EXPIRED-*  → expired, expiry -30d
//   AB-LIMIT-*    → limit_reached, 0 activations remaining
//   AB-DEACT-*    → deactivated
// ────────────────────────────────────────────────────────────────────────────

import { Router, type IRouter } from "express";
import { createHash } from "node:crypto";
import { eq } from "drizzle-orm";
import { licensesTable, licenseHeartbeatsTable } from "@workspace/db/schema";
import { db } from "../lib/db.js";

const router: IRouter = Router();

// Task #440 — Anti-piracy B. Plugin POSTs every 7 days from admin. Server
// returns one of four verdicts; plugin gates Pro features accordingly.
const GRACE_PERIOD_DAYS = 14;

type HeartbeatVerdict =
  | "ok"
  | "soft_warning"
  | "hard_disabled"
  | "domain_mismatch";

interface HeartbeatRequest {
  license_key?: string;
  domain?: string;
  plugin_version?: string;
  install_id?: string;
}

interface HeartbeatResponse {
  verdict: HeartbeatVerdict;
  status: string | null;
  expires_at: string | null;
  message: string;
  grace_period_days: number;
  domain_collision: boolean;
}

function hashKey(key: string): string {
  return createHash("sha256").update(key).digest("hex");
}

function heartbeatResponse(
  verdict: HeartbeatVerdict,
  message: string,
  status: string | null = null,
  expiresAt: Date | null = null,
  domainCollision = false,
): HeartbeatResponse {
  return {
    verdict,
    status,
    expires_at: expiresAt ? expiresAt.toISOString() : null,
    message,
    grace_period_days: GRACE_PERIOD_DAYS,
    domain_collision: domainCollision,
  };
}

type LicenseStatus =
  | "active"
  | "expired"
  | "invalid"
  | "limit_reached"
  | "deactivated";

const VALID_SKUS = [
  "schema",
  "og",
  "hreflang",
  "code",
  "aeo",
  "bundle",
] as const;
type Sku = (typeof VALID_SKUS)[number];

interface ValidateRequest {
  license_key: string;
  sku: string;
  site_domain: string;
}

interface ValidateResponse {
  valid: boolean;
  status: LicenseStatus;
  sku: Sku | null;
  activations_remaining: number;
  expires_at: string | null;
  message: string;
  mock: true;
}

interface DeactivateResponse {
  success: boolean;
  message: string;
  mock: true;
}

function isSku(value: unknown): value is Sku {
  return (
    typeof value === "string" && (VALID_SKUS as readonly string[]).includes(value)
  );
}

function mockValidate(
  license_key: string,
  sku: Sku,
  _site_domain: string,
): ValidateResponse {
  const key = license_key.trim().toUpperCase();
  const now = Date.now();
  const oneYear = 365 * 24 * 60 * 60 * 1000;
  const thirtyDays = 30 * 24 * 60 * 60 * 1000;

  if (key.startsWith("AB-VALID-")) {
    return {
      valid: true,
      status: "active",
      sku,
      activations_remaining: 1,
      expires_at: new Date(now + oneYear).toISOString(),
      message: "License is active.",
      mock: true,
    };
  }
  if (key.startsWith("AB-EXPIRED-")) {
    return {
      valid: false,
      status: "expired",
      sku,
      activations_remaining: 0,
      expires_at: new Date(now - thirtyDays).toISOString(),
      message: "License has expired. Please renew.",
      mock: true,
    };
  }
  if (key.startsWith("AB-LIMIT-")) {
    return {
      valid: false,
      status: "limit_reached",
      sku,
      activations_remaining: 0,
      expires_at: new Date(now + oneYear).toISOString(),
      message: "Activation limit reached for this license.",
      mock: true,
    };
  }
  if (key.startsWith("AB-DEACT-")) {
    return {
      valid: false,
      status: "deactivated",
      sku,
      activations_remaining: 0,
      expires_at: null,
      message: "License has been deactivated.",
      mock: true,
    };
  }
  return {
    valid: false,
    status: "invalid",
    sku,
    activations_remaining: 0,
    expires_at: null,
    message: "License key not found.",
    mock: true,
  };
}

router.post("/license/validate", (req, res) => {
  const body = (req.body ?? {}) as Partial<ValidateRequest>;
  const license_key =
    typeof body.license_key === "string" ? body.license_key.trim() : "";
  const skuRaw = typeof body.sku === "string" ? body.sku.trim() : "";
  const site_domain =
    typeof body.site_domain === "string" ? body.site_domain.trim() : "";

  if (!license_key) {
    res.status(400).json({
      valid: false,
      status: "invalid",
      sku: null,
      activations_remaining: 0,
      expires_at: null,
      message: "license_key is required",
      mock: true,
    } satisfies ValidateResponse);
    return;
  }
  if (!isSku(skuRaw)) {
    res.status(400).json({
      valid: false,
      status: "invalid",
      sku: null,
      activations_remaining: 0,
      expires_at: null,
      message: `sku must be one of: ${VALID_SKUS.join(", ")}`,
      mock: true,
    } satisfies ValidateResponse);
    return;
  }

  const result = mockValidate(license_key, skuRaw, site_domain);
  req.log.info(
    { sku: skuRaw, status: result.status, site_domain },
    "[MOCK] License validate",
  );
  res.json(result);
});

router.post("/license/deactivate", (req, res) => {
  const body = (req.body ?? {}) as {
    license_key?: string;
    site_domain?: string;
  };
  const license_key =
    typeof body.license_key === "string" ? body.license_key.trim() : "";
  const site_domain =
    typeof body.site_domain === "string" ? body.site_domain.trim() : "";

  if (!license_key) {
    res.status(400).json({
      success: false,
      message: "license_key is required",
      mock: true,
    } satisfies DeactivateResponse);
    return;
  }

  req.log.info({ site_domain }, "[MOCK] License deactivate");
  res.json({
    success: true,
    message: "License deactivated for this site.",
    mock: true,
  } satisfies DeactivateResponse);
});

// Task #440 — Phone-home heartbeat with strict domain binding.
// First successful heartbeat for a key binds it to (domain, install_id);
// any later heartbeat from a different (domain, install_id) returns
// `domain_mismatch` so the plugin can show an admin warning.
router.post("/license/heartbeat", async (req, res) => {
  const body = (req.body ?? {}) as HeartbeatRequest;
  const licenseKey = typeof body.license_key === "string" ? body.license_key.trim() : "";
  const domain = typeof body.domain === "string" ? body.domain.trim() : "";
  const pluginVersion =
    typeof body.plugin_version === "string" ? body.plugin_version.trim() : "";
  const installId = typeof body.install_id === "string" ? body.install_id.trim() : "";

  if (!licenseKey || !domain || !installId) {
    res
      .status(400)
      .json(
        heartbeatResponse(
          "hard_disabled",
          "license_key, domain and install_id are required",
        ),
      );
    return;
  }

  const keyHash = hashKey(licenseKey);

  const rows = await db
    .select()
    .from(licensesTable)
    .where(eq(licensesTable.licenseKey, licenseKey))
    .limit(1);
  const license = rows[0];

  if (!license) {
    await db.insert(licenseHeartbeatsTable).values({
      licenseKeyHash: keyHash,
      installId,
      domain,
      pluginVersion: pluginVersion || null,
      verdict: "hard_disabled",
    });
    req.log.warn({ domain, pluginVersion }, "[heartbeat] license not found");
    res.json(heartbeatResponse("hard_disabled", "License not found"));
    return;
  }

  // First heartbeat: bind domain + install_id. But still evaluate status /
  // expiry — if the license is already non-active or expired, return
  // soft_warning so the grace timeline starts on day 0 (per Anti-Piracy B).
  if (!license.boundDomain || !license.boundInstallId) {
    const nowFirst = new Date();
    const expiredFirst = license.expiresAt
      ? license.expiresAt.getTime() < nowFirst.getTime()
      : false;
    const nonActiveFirst = license.status !== "active" || expiredFirst;
    const firstVerdict: HeartbeatVerdict = nonActiveFirst ? "soft_warning" : "ok";

    await db
      .update(licensesTable)
      .set({
        boundDomain: domain,
        boundInstallId: installId,
        lastHeartbeatAt: nowFirst,
        updatedAt: nowFirst,
      })
      .where(eq(licensesTable.licenseKey, licenseKey));
    await db.insert(licenseHeartbeatsTable).values({
      licenseKeyHash: keyHash,
      installId,
      domain,
      pluginVersion: pluginVersion || null,
      verdict: firstVerdict,
    });
    req.log.info(
      { domain, sku: license.sku, verdict: firstVerdict, expiredFirst },
      "[heartbeat] first bind",
    );
    res.json(
      heartbeatResponse(
        firstVerdict,
        nonActiveFirst
          ? expiredFirst
            ? "License bound to this site, but it has expired. Pro features will be disabled after the grace period."
            : `License bound to this site, but status is ${license.status}. Pro features will be disabled after the grace period.`
          : "License bound to this site.",
        license.status,
        license.expiresAt,
      ),
    );
    return;
  }

  // Strict binding check.
  if (license.boundDomain !== domain || license.boundInstallId !== installId) {
    await db
      .update(licensesTable)
      .set({ collisionDetectedAt: new Date(), updatedAt: new Date() })
      .where(eq(licensesTable.licenseKey, licenseKey));
    await db.insert(licenseHeartbeatsTable).values({
      licenseKeyHash: keyHash,
      installId,
      domain,
      pluginVersion: pluginVersion || null,
      verdict: "domain_mismatch",
    });
    req.log.warn(
      { domain, boundDomain: license.boundDomain },
      "[heartbeat] domain mismatch",
    );
    res.json(
      heartbeatResponse(
        "domain_mismatch",
        `This license is bound to ${license.boundDomain}. Release it from the original site or contact support.`,
        license.status,
        license.expiresAt,
        true,
      ),
    );
    return;
  }

  // Bound install — but did we recently see a collision? Surface it here too
  // (within 30 days) so the originally bound site also shows a warning.
  const COLLISION_NOTIFY_WINDOW_DAYS = 30;
  const collisionFresh =
    license.collisionDetectedAt !== null &&
    Date.now() - license.collisionDetectedAt.getTime() <=
      COLLISION_NOTIFY_WINDOW_DAYS * 86400 * 1000;

  // Status / expiry check.
  const now = new Date();
  const expired = license.expiresAt ? license.expiresAt.getTime() < now.getTime() : false;
  if (license.status !== "active" || expired) {
    await db.insert(licenseHeartbeatsTable).values({
      licenseKeyHash: keyHash,
      installId,
      domain,
      pluginVersion: pluginVersion || null,
      verdict: "soft_warning",
    });
    req.log.info(
      { domain, status: license.status, expired },
      "[heartbeat] soft warning",
    );
    res.json(
      heartbeatResponse(
        "soft_warning",
        expired
          ? "License has expired. Pro features will be disabled after the grace period."
          : `License status is ${license.status}. Pro features will be disabled after the grace period.`,
        license.status,
        license.expiresAt,
        collisionFresh,
      ),
    );
    return;
  }

  await db
    .update(licensesTable)
    .set({ lastHeartbeatAt: now, updatedAt: now })
    .where(eq(licensesTable.licenseKey, licenseKey));
  await db.insert(licenseHeartbeatsTable).values({
    licenseKeyHash: keyHash,
    installId,
    domain,
    pluginVersion: pluginVersion || null,
    verdict: "ok",
  });
  res.json(
    heartbeatResponse(
      "ok",
      collisionFresh
        ? "License is active, but another site recently tried to use this key. Review any unauthorised installs."
        : "License is active.",
      "active",
      license.expiresAt,
      collisionFresh,
    ),
  );
});

// Task #567 — perpetual-activation reconciliation. A lapsed past purchaser
// whose local licence markers were cleared cannot be re-activated from the
// Joomla side alone (it no longer holds the key). The plugin instead sends its
// stable per-site `install_id` and asks: was THIS install ever bound to a real
// purchase? The binding is created on the first successful heartbeat
// (`boundInstallId`), so a match is proof of a genuine prior activation — and
// because install_id is an unguessable UUID, matching on it is what keeps this
// free of false positives (we never activate an install we have no record of).
//
// Eligibility is decided ONLY by `boundInstallId`. Domain is accepted for
// logging/corroboration but is intentionally NOT a sufficient match on its own:
// domains are public and spoofable, so domain-only recovery would risk
// activating an install that never paid. Those installs must re-enter their key.
interface ReconcileRequest {
  install_id?: string;
  domain?: string;
  plugin_version?: string;
}

interface ReconcileResponse {
  eligible: boolean;
  license_key: string | null;
  sku: Sku | null;
  status: string | null;
  expires_at: string | null;
  message: string;
}

router.post("/license/reconcile", async (req, res) => {
  const body = (req.body ?? {}) as ReconcileRequest;
  const installId =
    typeof body.install_id === "string" ? body.install_id.trim() : "";
  const domain = typeof body.domain === "string" ? body.domain.trim() : "";
  const pluginVersion =
    typeof body.plugin_version === "string" ? body.plugin_version.trim() : "";

  const ineligible = (message: string): ReconcileResponse => ({
    eligible: false,
    license_key: null,
    sku: null,
    status: null,
    expires_at: null,
    message,
  });

  if (!installId) {
    res.status(400).json(ineligible("install_id is required"));
    return;
  }

  // Authoritative match: this install_id was bound to a real purchase.
  const rows = await db
    .select()
    .from(licensesTable)
    .where(eq(licensesTable.boundInstallId, installId))
    .limit(1);
  const license = rows[0];

  if (!license) {
    await db.insert(licenseHeartbeatsTable).values({
      licenseKeyHash: "",
      installId,
      domain: domain || null,
      pluginVersion: pluginVersion || null,
      verdict: "reconcile_miss",
    });
    req.log.info(
      { domain, pluginVersion },
      "[reconcile] no prior purchase for install_id",
    );
    res.json(ineligible("No prior purchase found for this install."));
    return;
  }

  await db.insert(licenseHeartbeatsTable).values({
    licenseKeyHash: hashKey(license.licenseKey),
    installId,
    domain: domain || null,
    pluginVersion: pluginVersion || null,
    verdict: "reconciled",
  });
  req.log.info(
    { domain, sku: license.sku, status: license.status },
    "[reconcile] eligible — re-activating perpetual Pro",
  );

  // Hand the key back so the install can restore its Licenses tab + heartbeat.
  // Safe: it is returned only to the same install_id the server already bound.
  res.json({
    eligible: true,
    license_key: license.licenseKey,
    sku: isSku(license.sku) ? license.sku : "bundle",
    status: license.status,
    expires_at: license.expiresAt ? license.expiresAt.toISOString() : null,
    message:
      "Verified prior purchase. Pro re-activated for this install. Renew to resume updates + support.",
  } satisfies ReconcileResponse);
});

export default router;
