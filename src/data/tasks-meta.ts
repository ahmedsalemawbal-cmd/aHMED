import type { Stage } from '@/components/ui/stages';

export type TaskKind = 'first_message' | 'followup_3' | 'followup_7' | 'schedule_meeting' | 'meeting' | 'quote_followup' | 'retry' | 'custom';

/** Short label shown beside the shop name (Main.dc.html: «متابعة أولى»). */
export const TASK_KIND_LABEL: Record<TaskKind, string> = {
  first_message: 'الرسالة الأولى',
  followup_3: 'متابعة أولى',
  followup_7: 'متابعة ثانية',
  schedule_meeting: 'حدد اجتماعاً',
  meeting: 'اجتماع',
  quote_followup: 'متابعة العرض',
  retry: 'أعد المحاولة',
  custom: 'مهمة',
};

export function isTaskKind(v: string): v is TaskKind {
  return v in TASK_KIND_LABEL;
}

/** Calls for meetings, WhatsApp for everything that is a message. */
export function taskAction(kind: TaskKind): 'whatsapp' | 'call' {
  return kind === 'schedule_meeting' || kind === 'meeting' ? 'call' : 'whatsapp';
}

export interface TaskLead {
  id: string;
  businessName: string;
  stage: Stage;
  phone: string | null;
  doNotContact: boolean;
  contactName: string | null;
}

export interface TaskItem {
  id: string;
  title: string;
  kind: TaskKind;
  dueAt: Date;
  lead: TaskLead;
  /** first line of the prepared message for this task, if any */
  preview: string | null;
}

/** The action a task row offers, or none (no phone, or do_not_contact for WhatsApp). */
export function rowAction(t: TaskItem): 'whatsapp' | 'call' | null {
  const a = taskAction(t.kind);
  if (!t.lead.phone) return null;
  if (a === 'whatsapp' && t.lead.doNotContact) return null;
  return a;
}
