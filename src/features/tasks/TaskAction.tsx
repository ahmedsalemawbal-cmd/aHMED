import { Link } from 'react-router-dom';
import { Icon } from '@/components/ui/Icon';
import { rowAction, type TaskItem } from '@/data/tasks-meta';

/**
 * The row's action (Tasks.dc.html): «واتساب» opens the message for this
 * task, «اتصال» dials the shop. Nothing without a phone, and no WhatsApp for
 * a lead that asked not to be contacted.
 */
export function TaskAction({ t }: { t: TaskItem }) {
  const act = rowAction(t);
  if (act === 'whatsapp')
    return (
      <Link to={`/leads/${t.lead.id}/message?task=${t.id}`} className="md-btn md-btn-whatsapp md-btn-sm no-underline" aria-label={`واتساب ${t.lead.businessName}`}>
        <Icon name="chat" />
        <span>واتساب</span>
      </Link>
    );
  if (act === 'call' && t.lead.phone)
    return (
      <a className="md-btn md-btn-secondary md-btn-sm no-underline" href={`tel:+${t.lead.phone}`} aria-label={`اتصال ب${t.lead.businessName}`}>
        <Icon name="phone" />
        <span>اتصال</span>
      </a>
    );
  return null;
}
