import { useSearchParams } from 'react-router-dom';
import type { Stage } from '@/components/ui/stages';
import { parseStage } from './board';

/**
 * Pipeline state in the URL, replaced in place: ?stage= is the phone's
 * selected tab, ?activity= the activity filter (both layouts), so a reload,
 * a shared link or rotating a tablet keeps what was on screen.
 */
export function usePipelineView() {
  const [params, setParams] = useSearchParams();
  const update = (key: 'stage' | 'activity', value: string | null) => {
    setParams(
      (p) => {
        const next = new URLSearchParams(p);
        if (value === null) next.delete(key);
        else next.set(key, value);
        return next;
      },
      { replace: true },
    );
  };
  return {
    stage: parseStage(params.get('stage')),
    activity: params.get('activity') ?? 'all',
    pickStage: (s: Stage) => {
      update('stage', s);
    },
    setActivity: (a: string) => {
      update('activity', a === 'all' ? null : a);
    },
  };
}

export type PipelineView = ReturnType<typeof usePipelineView>;
