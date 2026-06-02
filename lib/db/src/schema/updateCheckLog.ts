import { pgTable, serial, varchar, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const updateCheckLogTable = pgTable("update_check_log", {
  id: serial("id").primaryKey(),
  keyHash: varchar("key_hash", { length: 64 }),
  domain: varchar("domain", { length: 255 }),
  currentVersion: varchar("current_version", { length: 32 }),
  packageSlug: varchar("package_slug", { length: 64 }).notNull(),
  verdict: varchar("verdict", { length: 16 }).notNull(),
  checkedAt: timestamp("checked_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
});

export const insertUpdateCheckLogSchema = createInsertSchema(updateCheckLogTable);
export type InsertUpdateCheckLog = z.infer<typeof insertUpdateCheckLogSchema>;
export type UpdateCheckLog = typeof updateCheckLogTable.$inferSelect;
