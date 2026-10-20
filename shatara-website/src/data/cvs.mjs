// Sample CVs from the approved design (code SH-1040 onward). Replace them with real candidates.
// Text such as the profession, religion, languages and skills is translated through the
// language files (cvJobs, religions, languages, skills), so each entry only stores keys.

export const cvs = [
  { name: { ar: 'ماريا سانتوس', en: 'Maria Santos' }, cc: 'ph', job: 'housemaid', gender: 'f', age: 29, years: 4, religion: 'other', langs: ['ar', 'en'], salary: '1,800', skills: ['cleaning', 'ironing', 'childcare'] },
  { name: { ar: 'فاطمة نامبوزي', en: 'Fatima Nambozi' }, cc: 'ug', job: 'housemaid', gender: 'f', age: 26, years: 0, religion: 'muslim', langs: ['en'], salary: '1,200', skills: ['cleaning', 'ironing'] },
  { name: { ar: 'أمينة وانجيرو', en: 'Amina Wanjiru' }, cc: 'ke', job: 'nanny', gender: 'f', age: 32, years: 6, religion: 'muslim', langs: ['ar', 'en'], salary: '1,500', skills: ['childcare', 'cooking', 'cleaning'] },
  { name: { ar: 'هيوت تسفاي', en: 'Hiwot Tesfaye' }, cc: 'et', job: 'cookF', gender: 'f', age: 34, years: 5, religion: 'other', langs: ['ar'], salary: '1,400', skills: ['cooking', 'cleaning'] },
  { name: { ar: 'محمد رحمن', en: 'Mohammed Rahman' }, cc: 'bd', job: 'driver', gender: 'm', age: 38, years: 9, religion: 'muslim', langs: ['ar', 'en'], salary: '1,700', skills: ['driving', 'maintenance'] },
  { name: { ar: 'راجيش كومار', en: 'Rajesh Kumar' }, cc: 'in', job: 'cookM', gender: 'm', age: 41, years: 12, religion: 'other', langs: ['en'], salary: '2,000', skills: ['cooking'] },
  { name: { ar: 'نيلوفار بيريرا', en: 'Nilufar Perera' }, cc: 'lk', job: 'nurse', gender: 'f', age: 36, years: 8, religion: 'other', langs: ['en'], salary: '2,200', skills: ['elderly', 'childcare'] },
  { name: { ar: 'جوي أبينا', en: 'Joy Abena' }, cc: 'gh', job: 'housemaid', gender: 'f', age: 24, years: 0, religion: 'other', langs: ['en'], salary: '1,200', skills: ['cleaning', 'ironing'] },
  { name: { ar: 'ريزا ديلا كروز', en: 'Riza Dela Cruz' }, cc: 'ph', job: 'elderlyF', gender: 'f', age: 39, years: 7, religion: 'other', langs: ['ar', 'en'], salary: '1,900', skills: ['elderly', 'cooking'] },
  { name: { ar: 'زينب نييونزيما', en: 'Zainab Niyonzima' }, cc: 'bi', job: 'housemaid', gender: 'f', age: 27, years: 2, religion: 'muslim', langs: ['ar'], salary: '1,200', skills: ['cleaning', 'ironing', 'cooking'] },
  { name: { ar: 'عبدالله أوتشينغ', en: 'Abdullah Ochieng' }, cc: 'ke', job: 'guard', gender: 'm', age: 35, years: 5, religion: 'muslim', langs: ['ar', 'en'], salary: '1,500', skills: ['security'] },
  { name: { ar: 'سوريش بانديت', en: 'Suresh Pandit' }, cc: 'in', job: 'gardener', gender: 'm', age: 44, years: 15, religion: 'other', langs: ['en'], salary: '1,400', skills: ['farming', 'irrigation'] }
].map((cv, i) => ({ ...cv, code: 'SH-' + (1040 + i) }));
