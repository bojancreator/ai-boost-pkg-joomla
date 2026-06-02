import { Router, type IRouter } from "express";
import healthRouter from "./health";
import licenseRouter from "./license";
import updatesRouter from "./updates";

const router: IRouter = Router();

router.use(healthRouter);
router.use(licenseRouter);
router.use(updatesRouter);

export default router;
