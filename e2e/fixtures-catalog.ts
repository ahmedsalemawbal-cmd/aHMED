// Mirrors bootstrap_owner(): the four activities, 13 weighted items and six services.
const GENERAL = [
  ['g1', 'التقييم 4.3 أو أعلى', 10, 'خرائط قوقل', 'تقييمه في قوقل أقل من 4.3'],
  ['g2', 'عدد مراجعات كافٍ مقارنة بالمنافسين', 5, 'خرائط قوقل', 'مراجعاته في قوقل قليلة'],
  ['g3', 'يرد على المراجعات', 5, 'خرائط قوقل', 'لا يرد على مراجعات قوقل'],
  ['g4', 'صور حديثة ومعلومات مكتملة', 5, 'خرائط قوقل', 'صوره ومعلوماته في قوقل ناقصة'],
  ['s1', 'حساب نشط، آخر نشر خلال 14 يوماً', 10, 'السوشيال ميديا', 'حسابه غير نشط'],
  ['s2', 'جودة التصوير والمحتوى', 10, 'السوشيال ميديا', 'التصوير والمحتوى ضعيف'],
  ['s3', 'ينشر فيديو أو ريلز', 10, 'السوشيال ميديا', 'لا ينشر فيديو أو ريلز'],
  ['s4', 'هوية بصرية موحدة', 5, 'السوشيال ميديا', 'هويته البصرية غير موحدة'],
  ['ad', 'يعلن حالياً إعلانات مدفوعة', 10, 'الإعلانات والموقع والواتساب', 'لا يعلن حالياً'],
  ['web', 'موقع أو صفحة هبوط أو رابط طلب وحجز', 10, 'الإعلانات والموقع والواتساب', 'بدون رابط طلب أو حجز'],
  ['wa', 'واتساب أعمال برد سريع', 5, 'الإعلانات والموقع والواتساب', 'لا يستخدم واتساب أعمال'],
  ['shop', 'لوحة وهوية واضحة، ومنيو أو قائمة أسعار مصورة', 10, 'داخل المحل', 'اللوحة أو المنيو غير واضحة'],
  ['off', 'عروض أو برنامج ولاء', 5, 'داخل المحل', 'بدون عروض أو برنامج ولاء'],
].map(([id, label, weight, group, weakness]) => ({ id, label, weight, group, weakness }));

const ALL = ['a-rest', 'a-cafe', 'a-dent', 'a-other'];

export const ACTIVITIES = [
  { id: 'a-rest', name: 'مطعم', icon: 'restaurant', sort_order: 1, default_service_ids: ['s-video', 's-maps'], checklist: { general: GENERAL, specific: [{ id: 'r1', label: 'صور احترافية للأطباق' }, { id: 'r2', label: 'حضور في تطبيقات التوصيل' }, { id: 'r3', label: 'منيو QR' }] } },
  { id: 'a-cafe', name: 'كافيه', icon: 'cafe', sort_order: 2, default_service_ids: ['s-video', 's-social', 's-ads'], checklist: { general: GENERAL, specific: [{ id: 'c1', label: 'محتوى يُظهر الأجواء' }] } },
  { id: 'a-dent', name: 'مركز أسنان', icon: 'dental', sort_order: 3, default_service_ids: ['s-landing', 's-ads', 's-video'], checklist: { general: GENERAL, specific: [{ id: 'd1', label: 'رابط حجز موعد' }] } },
  { id: 'a-other', name: 'أخرى', icon: 'store', sort_order: 4, default_service_ids: [], checklist: { general: GENERAL, specific: [] } },
];

export const SERVICES = [
  { id: 's-maps', name: 'إدارة ملف خرائط قوقل والمراجعات', billing: 'monthly', price_from: 800, activity_type_ids: ALL, weakness_item_ids: ['g1', 'g2', 'g3', 'g4'], is_active: true, sort_order: 1 },
  { id: 's-social', name: 'إدارة حسابات السوشيال ميديا', billing: 'monthly', price_from: 1200, activity_type_ids: ALL, weakness_item_ids: ['s1'], is_active: true, sort_order: 2 },
  { id: 's-video', name: 'تصوير وإنتاج فيديو وريلز', billing: 'monthly', price_from: 1500, activity_type_ids: ALL, weakness_item_ids: ['s2', 's3'], is_active: true, sort_order: 3 },
  { id: 's-ads', name: 'إدارة الحملات الإعلانية', billing: 'monthly', price_from: 1000, activity_type_ids: ALL, weakness_item_ids: ['ad'], is_active: true, sort_order: 4 },
  { id: 's-landing', name: 'صفحة هبوط', billing: 'one_time', price_from: 2000, activity_type_ids: ALL, weakness_item_ids: ['web'], is_active: true, sort_order: 5 },
  { id: 's-identity', name: 'تصميم هوية وقوالب', billing: 'one_time', price_from: 1500, activity_type_ids: ALL, weakness_item_ids: ['s4', 'shop'], is_active: true, sort_order: 6 },
];
