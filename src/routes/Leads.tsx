import { useMemo } from 'react';
import { AppShell } from '@/components/app/AppShell';
import { DeskLeads } from '@/features/leads/DeskLeads';
import { MobileLeads } from '@/features/leads/MobileLeads';
import { useLeadsList } from '@/features/leads/useLeadsList';
import { useIsDesktop } from '@/hooks/useMediaQuery';

/** Screen 5 · قائمة العملاء — design/screens/Leads.dc.html and DeskLeads.dc.html. */
export default function Leads() {
  const desktop = useIsDesktop();
  const list = useLeadsList();
  // relative times («اليوم»، «متأخرة منذ يومين») are read at the last fetch, so a refetch keeps them right
  const fetchedAt = list.leads.dataUpdatedAt;
  const now = useMemo(() => new Date(fetchedAt), [fetchedAt]);
  return <AppShell section="leads">{desktop ? <DeskLeads list={list} now={now} /> : <MobileLeads list={list} now={now} />}</AppShell>;
}
