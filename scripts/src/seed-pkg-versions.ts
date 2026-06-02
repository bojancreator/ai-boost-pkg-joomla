import pg from "pg";
import { drizzle } from "drizzle-orm/node-postgres";
import { pkgVersionsTable } from "@workspace/db/schema";

if (!process.env["DATABASE_URL"]) {
  throw new Error("DATABASE_URL is required");
}

const pool = new pg.Pool({ connectionString: process.env["DATABASE_URL"] });
const db = drizzle(pool);

const rows = [
  {
    packageSlug: "pkg_aiboost",
    version: "0.43.0",
    downloadUrl:
      "https://updates.aiboostnow.com/download/pkg_aiboost-0.43.0.zip",
    releaseNotes: "AI Boost free package — baseline release.",
  },
  {
    packageSlug: "pkg_aiboost",
    version: "0.44.0",
    downloadUrl:
      "https://updates.aiboostnow.com/download/pkg_aiboost-0.44.0.zip",
    releaseNotes:
      "Auto-update server hookup. No functional changes for free users.",
  },
  {
    packageSlug: "pkg_aiboost_pro",
    version: "0.43.0",
    downloadUrl:
      "https://updates.aiboostnow.com/download/pkg_aiboost_pro-0.43.0.zip",
    releaseNotes: "AI Boost Pro upgrade package — baseline release.",
  },
  {
    packageSlug: "pkg_aiboost_pro",
    version: "0.44.0",
    downloadUrl:
      "https://updates.aiboostnow.com/download/pkg_aiboost_pro-0.44.0.zip",
    releaseNotes: "Pro plugins refreshed for auto-update QA.",
  },
];

async function main(): Promise<void> {
  await pool.query("TRUNCATE TABLE pkg_versions RESTART IDENTITY");
  await db.insert(pkgVersionsTable).values(rows);
  console.log(`Seeded ${rows.length} pkg_versions rows.`);
  await pool.end();
}

main().catch((err: unknown) => {
  console.error(err);
  process.exit(1);
});
