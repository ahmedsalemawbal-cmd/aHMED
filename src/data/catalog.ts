import { useQuery } from '@tanstack/react-query';
import { supabase } from '@/lib/supabase';
import { parseChecklist, type Checklist } from '@/lib/scoring';
import type { CatalogService } from '@/lib/suggest';

export interface ActivityType {
  id: string;
  name: string;
  icon: string;
  checklist: Checklist;
  defaultServiceIds: string[];
}

export async function fetchCatalog(): Promise<{ activities: ActivityType[]; services: CatalogService[] }> {
  const [acts, svcs] = await Promise.all([
    supabase.from('activity_types').select('id, name, icon, checklist, default_service_ids, sort_order').order('sort_order'),
    supabase.from('services').select('id, name, billing, price_from, activity_type_ids, weakness_item_ids, is_active, sort_order').order('sort_order'),
  ]);
  if (acts.error) throw new Error(acts.error.message);
  if (svcs.error) throw new Error(svcs.error.message);
  return {
    activities: acts.data.map((a) => ({
      id: a.id,
      name: a.name,
      icon: a.icon,
      checklist: parseChecklist(a.checklist),
      defaultServiceIds: a.default_service_ids,
    })),
    services: svcs.data.map((s) => ({
      id: s.id,
      name: s.name,
      billing: s.billing === 'one_time' ? 'one_time' : 'monthly',
      priceFrom: s.price_from,
      activityTypeIds: s.activity_type_ids,
      weaknessItemIds: s.weakness_item_ids,
      isActive: s.is_active,
    })),
  };
}

export function useCatalog() {
  return useQuery({ queryKey: ['catalog'], queryFn: fetchCatalog, staleTime: 5 * 60_000 });
}

export interface LeadIndexRow {
  id: string;
  businessName: string;
  phoneE164: string | null;
  lat: number | null;
  lng: number | null;
}

/** Light index of all leads for the duplicate check (one user: hundreds of rows). */
export function useLeadIndex() {
  return useQuery({
    queryKey: ['lead-index'],
    queryFn: async (): Promise<LeadIndexRow[]> => {
      const { data, error } = await supabase.from('leads').select('id, business_name, phone_e164, lat, lng');
      if (error) throw new Error(error.message);
      return data.map((l) => ({ id: l.id, businessName: l.business_name, phoneE164: l.phone_e164, lat: l.lat, lng: l.lng }));
    },
  });
}
