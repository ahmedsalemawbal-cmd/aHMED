import { useMemo } from 'react';
import { AppShell } from '@/components/app/AppShell';
import { useLeads } from '@/data/leads';
import { DeskPipeline } from '@/features/pipeline/DeskPipeline';
import { MobilePipeline } from '@/features/pipeline/MobilePipeline';
import { usePipelineActions } from '@/features/pipeline/usePipelineActions';
import { usePipelineView } from '@/features/pipeline/usePipelineView';
import { useIsDesktop } from '@/hooks/useMediaQuery';

/** Screen 7 · Pipeline — design/screens/Pipeline.dc.html (phone, tablet portrait) and DeskPipeline.dc.html (from 1024px). */
export default function Pipeline() {
  const desktop = useIsDesktop();
  const leads = useLeads();
  const view = usePipelineView();
  // the sheets live here, so rotating a tablet mid-action keeps them open
  const actions = usePipelineActions();
  // days in a stage are counted at the last fetch (or local move), so a refetch keeps them right
  const fetchedAt = leads.dataUpdatedAt;
  const now = useMemo(() => new Date(fetchedAt), [fetchedAt]);
  return (
    <AppShell section="pipeline">
      {desktop ? <DeskPipeline leads={leads} now={now} view={view} actions={actions} /> : <MobilePipeline leads={leads} now={now} view={view} actions={actions} />}
      {actions.sheets}
    </AppShell>
  );
}
