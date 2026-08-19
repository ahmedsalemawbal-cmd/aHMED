import { useEffect, useState } from 'react';
import { supabase } from './supabase';

export type Me = {
  id: number;
  displayName: string;
  role: string;
  roleLabel: string;
  caps: Set<string>;
};

/**
 * هوية المستخدم الحالي وصلاحياته.
 *
 * تُقرأ مرّة عند الدخول وتبقى في الذاكرة: قراءتها مع كل شاشة تعني
 * استعلامين زائدين في كل تنقّل. والقاعدة تُنفّذ الصلاحية على كل حال —
 * فما هنا لإخفاء ما لا يَقدر عليه المستخدم، لا لمنعه.
 */
export function useMe() {
  const [me, setMe] = useState<Me | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let alive = true;

    const load = async () => {
      const { data: { session } } = await supabase.auth.getSession();

      if (!session) {
        if (alive) { setMe(null); setLoading(false); }
        return;
      }

      const { data: row } = await supabase
        .from('sch_users')
        .select('id, display_name, role, sch_roles(label)')
        .eq('auth_id', session.user.id)
        .single();

      if (!row) {
        // حسابٌ في Supabase بلا صفٍّ في جدول المستخدمين: يُسجَّل خروجه
        // بدل أن يُترك في حالة نصف داخل يعطّل كل شاشة بعدها.
        await supabase.auth.signOut();
        if (alive) { setMe(null); setLoading(false); }
        return;
      }

      // الصلاحيات المخصّصة تُلغي صلاحيات الدور — كما في القاعدة تمامًا
      const [own, byRole] = await Promise.all([
        supabase.from('sch_user_caps').select('cap').eq('user_id', row.id),
        supabase.from('sch_role_caps').select('cap').eq('role', row.role),
      ]);

      const caps = (own.data?.length ? own.data : byRole.data ?? []).map((c) => c.cap);
      const label = (row as { sch_roles?: { label?: string } }).sch_roles?.label ?? row.role;

      if (alive) {
        setMe({
          id: row.id,
          displayName: row.display_name,
          role: row.role,
          roleLabel: label,
          caps: new Set(caps),
        });
        setLoading(false);
      }
    };

    void load();
    const { data: sub } = supabase.auth.onAuthStateChange(() => void load());

    return () => { alive = false; sub.subscription.unsubscribe(); };
  }, []);

  return { me, loading, can: (cap: string) => me?.caps.has(cap) ?? false };
}
