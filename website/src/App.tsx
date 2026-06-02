import { BrowserRouter, Routes, Route } from 'react-router-dom'
import { Helmet } from 'react-helmet-async'
import { LandingPage } from './components/LandingPage'
import { BlogListPage } from './components/BlogListPage'
import { BlogPostPage } from './components/BlogPostPage'
import { FaqPage } from './components/FaqPage'
import { DocsPage } from './components/DocsPage'
import { DocDetailPage } from './components/DocDetailPage'
import { PricingPage } from './components/PricingPage'
import { FeaturesPage } from './components/FeaturesPage'
import { PluginsIndexPage } from './components/PluginsIndexPage'
import { PluginPage } from './components/PluginPage'

const GSC_VERIFICATION = import.meta.env.VITE_GOOGLE_SITE_VERIFICATION as string | undefined

export default function App() {
  return (
    <BrowserRouter>
      {GSC_VERIFICATION && (
        <Helmet>
          <meta name="google-site-verification" content={GSC_VERIFICATION} />
        </Helmet>
      )}
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/features" element={<FeaturesPage />} />
        <Route path="/blog" element={<BlogListPage />} />
        <Route path="/blog/:slug" element={<BlogPostPage />} />
        <Route path="/faq" element={<FaqPage />} />
        <Route path="/docs" element={<DocsPage />} />
        <Route path="/docs/:section" element={<DocDetailPage />} />
        <Route path="/pricing" element={<PricingPage />} />
        <Route path="/plugins" element={<PluginsIndexPage />} />
        <Route path="/plugins/:slug" element={<PluginPage />} />
        <Route path="*" element={<LandingPage />} />
      </Routes>
    </BrowserRouter>
  )
}
