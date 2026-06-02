import { pgTable, serial, varchar, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const licenseHeartbeatsTable = pgTable("license_heartbeats", {
  id: serial("id").primaryKey(),
  licenseKeyHash: varchar("license_key_hash", { length: 64 }).notNull(),
  installId: varchar("install_id", { length: 36 }),
  domain: varchar("domain", { length: 255 }),
  pluginVersion: varchar("plugin_version", { length: 32 }),
  verdict: varchar("verdict", { length: 32 }).notNull(),
  checkedAt: timestamp("checked_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
});

export const insertLicenseHeartbeatSchema = createInsertSchema(
  licenseHeartbeatsTable,
);
export type InsertLicenseHeartbeat = z.infer<typeof insertLicenseHeartbeatSchema>;
export type LicenseHeartbeat = typeof licenseHeartbeatsTable.$inferSelect;
