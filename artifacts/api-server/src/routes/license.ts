import { Router, type IRouter } from "express";

const router: IRouter = Router();

type LicenseStatus = "active" | "expiring_soon" | "invalid" | "deactivated";

interface GumroadPurchase {
  email?: string;
  created_at?: string;
  sale_timestamp?: string;
  variants?: string;
  refunded?: boolean;
  chargebacked?: boolean;
  subscription_ended_at?: string | null;
}

interface GumroadVerifyResponse {
  success: boolean;
  uses?: number;
  message?: string;
  purchase?: GumroadPurchase;
}

function deriveTier(variants: string): "starter" | "developer" | "agency" {
  const v = variants.toLowerCase();
  if (v.includes("agency")) return "agency";
  if (v.includes("developer")) return "developer";
  return "starter";
}

function maxUsesForTier(tier: string): number {
  if (tier === "agency") return -1;
  if (tier === "developer") return 5;
  return 1;
}

function deriveActiveStatus(purchase: GumroadPurchase): LicenseStatus {
  const endedAt = purchase.subscription_ended_at;
  if (endedAt) {
    const daysUntilExpiry =
      (new Date(endedAt).getTime() - Date.now()) / (1000 * 60 * 60 * 24);
    if (daysUntilExpiry <= 0) {
      return "deactivated";
    }
    if (daysUntilExpiry <= 30) {
      return "expiring_soon";
    }
  }
  return "active";
}

router.post("/license/validate", async (req, res) => {
  const body = req.body as Record<string, unknown>;
  const license_key =
    typeof body["license_key"] === "string" ? body["license_key"].trim() : "";
  const site_url =
    typeof body["site_url"] === "string" ? body["site_url"].trim() : "";

  if (!license_key) {
    res.status(400).json({
      valid: false,
      status: "invalid" as LicenseStatus,
      error: "license_key is required",
    });
    return;
  }

  const productId = process.env["GUMROAD_PRODUCT_ID"];
  if (!productId) {
    req.log.warn("GUMROAD_PRODUCT_ID is not set — license validation unavailable");
    res.status(503).json({
      valid: false,
      status: "invalid" as LicenseStatus,
      error: "License validation not configured on server",
    });
    return;
  }

  try {
    const gumroadBody = new URLSearchParams({
      product_id: productId,
      license_key,
      increment_uses_count: "false",
    });

    const gumroadRes = await fetch(
      "https://api.gumroad.com/v2/licenses/verify",
      {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: gumroadBody.toString(),
      },
    );

    const data = (await gumroadRes.json()) as GumroadVerifyResponse;

    if (!data.success || !data.purchase) {
      res.json({
        valid: false,
        status: "invalid" as LicenseStatus,
        error: data.message ?? "License key not found or invalid",
      });
      return;
    }

    const purchase = data.purchase;

    if (purchase.refunded || purchase.chargebacked) {
      res.json({
        valid: false,
        status: "deactivated" as LicenseStatus,
        error: "This license has been refunded and is no longer valid",
      });
      return;
    }

    const email = purchase.email ?? "";
    const activatedAt = purchase.sale_timestamp ?? purchase.created_at ?? "";
    const uses = data.uses ?? 0;
    const tier = deriveTier(purchase.variants ?? "");
    const maxUses = maxUsesForTier(tier);
    const remainingActivations =
      maxUses === -1 ? -1 : Math.max(0, maxUses - uses);
    const status = deriveActiveStatus(purchase);

    if (status === "deactivated") {
      res.json({
        valid: false,
        status: "deactivated" as LicenseStatus,
        error: "This subscription has expired and is no longer valid",
      });
      return;
    }

    res.json({
      valid: true,
      status,
      tier,
      email,
      activated_at: activatedAt,
      uses,
      max_uses: maxUses,
      remaining_activations: remainingActivations,
      site_url,
    });
  } catch (err) {
    req.log.error({ err }, "Gumroad API request failed");
    res.status(502).json({
      valid: false,
      status: "invalid" as LicenseStatus,
      error: "Could not reach license verification server",
    });
  }
});

export default router;
