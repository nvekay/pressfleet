CREATE TABLE "leads" (
	"id" text PRIMARY KEY NOT NULL,
	"site_id" text NOT NULL,
	"wp_lead_id" integer NOT NULL,
	"name" text NOT NULL,
	"contact" text NOT NULL,
	"message" text NOT NULL,
	"page" text NOT NULL,
	"submitted_at" timestamp with time zone NOT NULL,
	"received_at" timestamp with time zone DEFAULT now() NOT NULL,
	"notified_at" timestamp with time zone,
	"notify_error" text
);
--> statement-breakpoint
ALTER TABLE "leads" ADD CONSTRAINT "leads_site_id_sites_id_fk" FOREIGN KEY ("site_id") REFERENCES "public"."sites"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE UNIQUE INDEX "leads_site_lead_idx" ON "leads" USING btree ("site_id","wp_lead_id");--> statement-breakpoint
CREATE INDEX "leads_received_idx" ON "leads" USING btree ("received_at");