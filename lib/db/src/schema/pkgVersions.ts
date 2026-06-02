import { pgTable, serial, varchar, text, timestamp } from "drizzle-orm/pg-core";
import { createInsertSchema } from "drizzle-zod";
import { z } from "zod/v4";

export const pkgVersionsTable = pgTable("pkg_versions", {
  id: serial("id").primaryKey(),
  packageSlug: varchar("package_slug", { length: 64 }).notNull(),
  version: varchar("version", { length: 32 }).notNull(),
  downloadUrl: text("download_url").notNull(),
  releaseNotes: text("release_notes"),
  releasedAt: timestamp("released_at", { withTimezone: true })
    .notNull()
    .defaultNow(),
});

export const insertPkgVersionSchema = createInsertSchema(pkgVersionsTable);
export type InsertPkgVersion = z.infer<typeof insertPkgVersionSchema>;
export type PkgVersion = typeof pkgVersionsTable.$inferSelect;
