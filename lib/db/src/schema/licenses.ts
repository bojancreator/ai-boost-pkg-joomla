import { pgTable, text, timestamp, integer, jsonb, varchar } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const licensesTable = pgTable("licenses", {
  licenseKey: text("license_key").primaryKey(),
  sku: text("sku").notNull(),
  status: text("status").notNull(),
  activationsRemaining: integer("activations_remaining").notNull().default(0),
  activatedDomains: jsonb("activated_domains")
    .$type<string[]>()
    .notNull()
    .default([]),
  expiresAt: timestamp("expires_at", { withTimezone: true }),
  // Task #440 — strict domain binding (first heartbeat wins).
  boundDomain: varchar("bound_domain", { length: 255 }),
  boundInstallId: varchar("bound_install_id", { length: 36 }),
  lastHeartbeatAt: timestamp("last_heartbeat_at", { withTimezone: true }),
  // Set the moment we see a heartbeat from a different (domain, install_id).
  // Both the bound install AND the conflicting install see the collision.
  collisionDetectedAt: timestamp("collision_detected_at", { withTimezone: true }),
  createdAt: timestamp("created_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
  updatedAt: timestamp("updated_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
});

export const insertLicenseSchema = createInsertSchema(licensesTable);
export type InsertLicense = z.infer<typeof insertLicenseSchema>;
export type License = typeof licensesTable.$inferSelect;
