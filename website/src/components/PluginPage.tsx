import { useParams } from 'react-router-dom'
import { pluginBySlug } from '../data/pluginsData'
import { PluginSalesPage } from './PluginSalesPage'
import { ComingSoonPluginPage } from './ComingSoonPluginPage'
import { PluginsIndexPage } from './PluginsIndexPage'

export function PluginPage() {
  const { slug } = useParams<{ slug: string }>()
  const plugin = slug ? pluginBySlug(slug) : undefined

  if (!plugin) return <PluginsIndexPage />

  if (plugin.status === 'live') {
    return <PluginSalesPage plugin={plugin} />
  }

  return <ComingSoonPluginPage plugin={plugin} />
}
