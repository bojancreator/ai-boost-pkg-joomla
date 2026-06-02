import { db, licensesTable } from "@workspace/db";
import { sql } from "drizzle-orm";

type SeedRow = {
  licenseKey: string;
  sku: string;
  status: string;
  activationsRemaining: number;
  expiresAt: Date | null;
};

const oneYear = () => new Date(Date.now() + 365 * 24 * 60 * 60 * 1000);
const thirtyDaysAgo = () => new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);

const SEED: SeedRow[] = [
  { licenseKey: "AB-VALID-SCHEMA-001", sku: "schema", status: "active", activationsRemaining: 1, expiresAt: oneYear() },
  { licenseKey: "AB-VALID-OG-001", sku: "og", status: "active", activationsRemaining: 1, expiresAt: oneYear() },
  { licenseKey: "AB-VALID-HREFLANG-001", sku: "hreflang", status: "active", activationsRemaining: 1, expiresAt: oneYear() },
  { licenseKey: "AB-VALID-CODE-001", sku: "code", status: "active", activationsRemaining: 1, expiresAt: oneYear() },
  { licenseKey: "AB-VALID-AEO-001", sku: "aeo", status: "active", activationsRemaining: 1, expiresAt: oneYear() },
  { licenseKey: "AB-VALID-BUNDLE-001", sku: "bundle", status: "active", activationsRemaining: 1, expiresAt: oneYear() },
  { licenseKey: "AB-EXPIRED-SCHEMA-001", sku: "schema", status: "expired", activationsRemaining: 0, expiresAt: thirtyDaysAgo() },
  { licenseKey: "AB-LIMIT-AEO-001", sku: "aeo", status: "limit_reached", activationsRemaining: 0, expiresAt: oneYear() },
  { licenseKey: "AB-DEACT-CODE-001", sku: "code", status: "deactivated", activationsRemaining: 0, expiresAt: null },
];

async function main(): Promise<void> {
  console.log("[seed-license-mock] truncating licenses table...");
  await db.execute(sql`TRUNCATE TABLE licenses`);

  console.log(`[seed-license-mock] inserting ${SEED.length} mock licenses...`);
  for (const row of SEED) {
    await db.insert(licensesTable).values({
      licenseKey: row.licenseKey,
      sku: row.sku,
      status: row.status,
      activationsRemaining: row.activationsRemaining,
      activatedDomains: [],
      expiresAt: row.expiresAt,
    });
    console.log(`  ✓ ${row.licenseKey} → ${row.status}`);
  }
  console.log("[seed-license-mock] done.");
  process.exit(0);
}

main().catch((err) => {
  console.error("[seed-license-mock] FAILED:", err);
  process.exit(1);
});
