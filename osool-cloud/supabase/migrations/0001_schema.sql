-- مخطّط أُصول على PostgreSQL — مولَّد من مخطّط MySQL الأصلي
-- 71 جدولًا

CREATE TABLE public.sch_students (
    id bigint GENERATED ALWAYS AS IDENTITY,
    full_name varchar(190) NOT NULL,
    first_name varchar(60),
    father_name varchar(60),
    grand_name varchar(60),
    family_name varchar(60),
    name_en varchar(190),
    academic_no varchar(20),
    student_no varchar(20),
    id_type text DEFAULT 'national' NOT NULL,
    nationality varchar(60),
    photo_file varchar(190),
    enrolled_at date,
    national_id varchar(20),
    birth_date date,
    gender text,
    stage varchar(30),
    grade_level varchar(30),
    section varchar(30),
    qr_token char(32) NOT NULL,
    user_id bigint,
    badge_token char(32),
    badge_printed_at timestamptz,
    custody_state text DEFAULT 'home' NOT NULL,
    custody_at timestamptz,
    status text DEFAULT 'active' NOT NULL,
    notes text,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT students_uniq_qr UNIQUE (qr_token),
    CONSTRAINT students_uniq_national UNIQUE (national_id),
    CONSTRAINT students_uniq_academic UNIQUE (academic_no),
    CONSTRAINT students_uniq_badge UNIQUE (badge_token),
    CONSTRAINT students_uniq_user UNIQUE (user_id),
    CONSTRAINT students_uniq_studentno UNIQUE (student_no)
);

CREATE TABLE public.sch_pickup_delegations (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    person_name varchar(190) NOT NULL,
    relation varchar(60),
    id_number varchar(40),
    phone varchar(30),
    granted_by bigint NOT NULL,
    valid_from timestamptz NOT NULL,
    valid_until timestamptz NOT NULL,
    status varchar(12) DEFAULT 'active' NOT NULL,
    used_at timestamptz,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_guardian_student (
    id bigint GENERATED ALWAYS AS IDENTITY,
    guardian_user_id bigint NOT NULL,
    student_id bigint NOT NULL,
    relation varchar(30),
    is_primary boolean DEFAULT false NOT NULL,
    can_pickup boolean DEFAULT true NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT guardian_student_uniq_link UNIQUE (guardian_user_id, student_id)
);

CREATE TABLE public.sch_refresh_tokens (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    token_hash char(64) NOT NULL,
    device_id varchar(100) NOT NULL,
    device_name varchar(190),
    expires_at timestamptz NOT NULL,
    revoked_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT refresh_tokens_uniq_hash UNIQUE (token_hash)
);

CREATE TABLE public.sch_audit_log (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint,
    action varchar(60) NOT NULL,
    object_type varchar(40),
    object_id bigint,
    meta text,
    ip varchar(45),
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_employees (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    employee_no varchar(40),
    job_title varchar(120),
    stage_scope text DEFAULT 'none' NOT NULL,
    weekly_quota smallint DEFAULT 24 NOT NULL,
    department varchar(120),
    phone varchar(30),
    national_id varchar(20),
    hire_date date,
    badge_token char(32),
    badge_printed_at timestamptz,
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT employees_uniq_user UNIQUE (user_id),
    CONSTRAINT employees_uniq_badge UNIQUE (badge_token)
);

CREATE TABLE public.sch_academic_years (
    id bigint GENERATED ALWAYS AS IDENTITY,
    name varchar(60) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    is_current boolean DEFAULT false NOT NULL,
    tt_status text DEFAULT 'draft' NOT NULL,
    tt_sent_by bigint,
    tt_sent_at timestamptz,
    tt_ok_by bigint,
    tt_ok_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT academic_years_uniq_name UNIQUE (name)
);

CREATE TABLE public.sch_classes (
    id bigint GENERATED ALWAYS AS IDENTITY,
    year_id bigint NOT NULL,
    stage varchar(30) NOT NULL,
    grade_level varchar(60) NOT NULL,
    section varchar(30) NOT NULL,
    capacity smallint DEFAULT 30 NOT NULL,
    homeroom_teacher_id bigint,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT classes_uniq_class UNIQUE (year_id, grade_level, section)
);

CREATE TABLE public.sch_enrollments (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    class_id bigint NOT NULL,
    year_id bigint NOT NULL,
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT enrollments_uniq_enrollment UNIQUE (student_id, year_id)
);

CREATE TABLE public.sch_guardians (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    parent_no varchar(20),
    photo_file varchar(190),
    first_name varchar(60),
    father_name varchar(60),
    grand_name varchar(60),
    family_name varchar(60),
    id_type text DEFAULT 'national' NOT NULL,
    city varchar(80),
    national_id varchar(20),
    phone varchar(30),
    alt_phone varchar(30),
    workplace varchar(150),
    address varchar(255),
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT guardians_uniq_user UNIQUE (user_id),
    CONSTRAINT guardians_uniq_national UNIQUE (national_id),
    CONSTRAINT guardians_uniq_parentno UNIQUE (parent_no)
);

CREATE TABLE public.sch_buses (
    id bigint GENERATED ALWAYS AS IDENTITY,
    plate_no varchar(20) NOT NULL,
    model varchar(80),
    capacity smallint DEFAULT 20 NOT NULL,
    driver_user_id bigint,
    supervisor_user_id bigint,
    registration_expiry date,
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT buses_uniq_plate UNIQUE (plate_no)
);

CREATE TABLE public.sch_routes (
    id bigint GENERATED ALWAYS AS IDENTITY,
    name varchar(120) NOT NULL,
    bus_id bigint,
    direction text DEFAULT 'both' NOT NULL,
    status text DEFAULT 'active' NOT NULL,
    duration_min smallint DEFAULT 0 NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_route_stops (
    id bigint GENERATED ALWAYS AS IDENTITY,
    route_id bigint NOT NULL,
    name varchar(150) NOT NULL,
    lat numeric(10,7),
    lng numeric(10,7),
    sequence smallint DEFAULT 1 NOT NULL,
    pickup_time time,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_transport_subs (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    route_id bigint NOT NULL,
    stop_id bigint,
    year_id bigint NOT NULL,
    fee_amount numeric(10,2) DEFAULT 0 NOT NULL,
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT transport_subs_uniq_sub UNIQUE (student_id, year_id)
);

CREATE TABLE public.sch_trips (
    id bigint GENERATED ALWAYS AS IDENTITY,
    route_id bigint NOT NULL,
    bus_id bigint,
    driver_user_id bigint,
    direction text NOT NULL,
    trip_date date NOT NULL,
    started_at timestamptz,
    ended_at timestamptz,
    status text DEFAULT 'planned' NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT trips_uniq_trip UNIQUE (route_id, trip_date, direction)
);

CREATE TABLE public.sch_trip_events (
    id bigint GENERATED ALWAYS AS IDENTITY,
    trip_id bigint NOT NULL,
    student_id bigint NOT NULL,
    type text NOT NULL,
    lat numeric(10,7),
    lng numeric(10,7),
    client_uuid char(36) NOT NULL,
    recorded_by bigint,
    occurred_at timestamptz NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT trip_events_uniq_client UNIQUE (client_uuid)
);

CREATE TABLE public.sch_vehicle_positions (
    id bigint GENERATED ALWAYS AS IDENTITY,
    trip_id bigint NOT NULL,
    lat numeric(10,7) NOT NULL,
    lng numeric(10,7) NOT NULL,
    speed smallint,
    recorded_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_attendance (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    class_id bigint,
    att_date date NOT NULL,
    status text DEFAULT 'present' NOT NULL,
    method text DEFAULT 'manual' NOT NULL,
    checked_in_at timestamptz,
    minutes_late smallint DEFAULT 0 NOT NULL,
    note varchar(255),
    recorded_by bigint,
    recorded_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT attendance_uniq_day UNIQUE (student_id, att_date)
);

CREATE TABLE public.sch_subjects (
    id bigint GENERATED ALWAYS AS IDENTITY,
    name varchar(120) NOT NULL,
    code varchar(30),
    stage varchar(30),
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT subjects_uniq_code UNIQUE (code)
);

CREATE TABLE public.sch_class_subjects (
    id bigint GENERATED ALWAYS AS IDENTITY,
    class_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    teacher_user_id bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT class_subjects_uniq_cs UNIQUE (class_id, subject_id)
);

CREATE TABLE public.sch_timetable (
    id bigint GENERATED ALWAYS AS IDENTITY,
    class_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    teacher_user_id bigint,
    day_of_week smallint NOT NULL,
    period_no smallint NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT timetable_uniq_slot UNIQUE (class_id, day_of_week, period_no)
);

CREATE TABLE public.sch_assignments (
    id bigint GENERATED ALWAYS AS IDENTITY,
    class_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    title varchar(190) NOT NULL,
    description text,
    due_date date,
    max_score numeric(6,2) DEFAULT 10 NOT NULL,
    created_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_exams (
    id bigint GENERATED ALWAYS AS IDENTITY,
    class_id bigint NOT NULL,
    subject_id bigint NOT NULL,
    title varchar(190) NOT NULL,
    exam_type text DEFAULT 'quiz' NOT NULL,
    exam_date date,
    max_score numeric(6,2) DEFAULT 100 NOT NULL,
    weight numeric(5,2) DEFAULT 100 NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_exam_results (
    id bigint GENERATED ALWAYS AS IDENTITY,
    exam_id bigint NOT NULL,
    student_id bigint NOT NULL,
    score numeric(6,2),
    note varchar(255),
    recorded_by bigint,
    recorded_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT exam_results_uniq_result UNIQUE (exam_id, student_id)
);

CREATE TABLE public.sch_fee_plans (
    id bigint GENERATED ALWAYS AS IDENTITY,
    year_id bigint NOT NULL,
    name varchar(150) NOT NULL,
    stage varchar(30),
    amount numeric(10,2) DEFAULT 0 NOT NULL,
    installments smallint DEFAULT 1 NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_invoices (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    year_id bigint NOT NULL,
    plan_id bigint,
    gross numeric(10,2) DEFAULT 0 NOT NULL,
    discount_pct numeric(5,2) DEFAULT 0 NOT NULL,
    total numeric(10,2) DEFAULT 0 NOT NULL,
    discount numeric(10,2) DEFAULT 0 NOT NULL,
    paid numeric(10,2) DEFAULT 0 NOT NULL,
    pay_mode text DEFAULT 'full' NOT NULL,
    grant_reason varchar(190),
    granted_by bigint,
    status text DEFAULT 'open' NOT NULL,
    due_date date,
    created_by bigint,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_installments (
    id bigint GENERATED ALWAYS AS IDENTITY,
    invoice_id bigint NOT NULL,
    seq smallint NOT NULL,
    amount numeric(10,2) NOT NULL,
    due_date date,
    paid numeric(10,2) DEFAULT 0 NOT NULL,
    status text DEFAULT 'open' NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT installments_uniq_seq UNIQUE (invoice_id, seq)
);

CREATE TABLE public.sch_payments (
    id bigint GENERATED ALWAYS AS IDENTITY,
    invoice_id bigint NOT NULL,
    installment_id bigint,
    amount numeric(10,2) NOT NULL,
    method text DEFAULT 'cash' NOT NULL,
    reference varchar(120),
    client_uuid varchar(64),
    refunded_at timestamptz,
    refund_reason varchar(190),
    received_by bigint,
    paid_at timestamptz NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT payments_uniq_client UNIQUE (client_uuid)
);

CREATE TABLE public.sch_messages (
    id bigint GENERATED ALWAYS AS IDENTITY,
    title varchar(190) NOT NULL,
    body text NOT NULL,
    audience text DEFAULT 'all_guardians' NOT NULL,
    audience_id bigint,
    created_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_notifications (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    title varchar(190) NOT NULL,
    body text,
    ref_type varchar(40),
    ref_id bigint,
    read_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_deliveries (
    id bigint GENERATED ALWAYS AS IDENTITY,
    notification_id bigint NOT NULL,
    user_id bigint NOT NULL,
    channel varchar(20) DEFAULT 'email' NOT NULL,
    status text DEFAULT 'queued' NOT NULL,
    attempts smallint DEFAULT 0 NOT NULL,
    error varchar(190),
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_pickup_calls (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    caller_id bigint NOT NULL,
    caller_name varchar(150) NOT NULL,
    picker_key varchar(24) NOT NULL,
    note varchar(190),
    status text DEFAULT 'open' NOT NULL,
    ack_by bigint,
    ack_at timestamptz,
    closed_by bigint,
    closed_at timestamptz,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_push_subs (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    endpoint text NOT NULL,
    endpoint_hash char(64) NOT NULL,
    p256dh varchar(190) NOT NULL,
    auth varchar(64) NOT NULL,
    device varchar(190),
    status text DEFAULT 'active' NOT NULL,
    last_sent_at timestamptz,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT push_subs_uniq_endpoint UNIQUE (endpoint_hash)
);

CREATE TABLE public.sch_accounts (
    id bigint GENERATED ALWAYS AS IDENTITY,
    code varchar(20) NOT NULL,
    name varchar(150) NOT NULL,
    type text NOT NULL,
    parent_id bigint,
    is_system boolean DEFAULT false NOT NULL,
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT accounts_uniq_code UNIQUE (code)
);

CREATE TABLE public.sch_journal_entries (
    id bigint GENERATED ALWAYS AS IDENTITY,
    entry_no varchar(30) NOT NULL,
    entry_date date NOT NULL,
    description varchar(255) NOT NULL,
    ref_type varchar(30),
    ref_id bigint,
    total numeric(14,2) DEFAULT 0 NOT NULL,
    status text DEFAULT 'draft' NOT NULL,
    created_by bigint,
    posted_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT journal_entries_uniq_no UNIQUE (entry_no)
);

CREATE TABLE public.sch_journal_lines (
    id bigint GENERATED ALWAYS AS IDENTITY,
    entry_id bigint NOT NULL,
    account_id bigint NOT NULL,
    debit numeric(14,2) DEFAULT 0 NOT NULL,
    credit numeric(14,2) DEFAULT 0 NOT NULL,
    description varchar(255),
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_contracts (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    contract_no varchar(40),
    contract_type text DEFAULT 'full_time' NOT NULL,
    start_date date NOT NULL,
    end_date date,
    basic_salary numeric(10,2) DEFAULT 0 NOT NULL,
    allowances numeric(10,2) DEFAULT 0 NOT NULL,
    status text DEFAULT 'active' NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_leaves (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    leave_type text DEFAULT 'annual' NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    days smallint DEFAULT 1 NOT NULL,
    reason varchar(255),
    status text DEFAULT 'pending' NOT NULL,
    decided_by bigint,
    decided_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_payroll_runs (
    id bigint GENERATED ALWAYS AS IDENTITY,
    period_year smallint NOT NULL,
    period_month smallint NOT NULL,
    total numeric(14,2) DEFAULT 0 NOT NULL,
    headcount smallint DEFAULT 0 NOT NULL,
    status text DEFAULT 'draft' NOT NULL,
    created_by bigint,
    posted_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT payroll_runs_uniq_period UNIQUE (period_year, period_month)
);

CREATE TABLE public.sch_payslips (
    id bigint GENERATED ALWAYS AS IDENTITY,
    run_id bigint NOT NULL,
    user_id bigint NOT NULL,
    basic numeric(10,2) DEFAULT 0 NOT NULL,
    allowances numeric(10,2) DEFAULT 0 NOT NULL,
    deductions numeric(10,2) DEFAULT 0 NOT NULL,
    net numeric(10,2) DEFAULT 0 NOT NULL,
    note varchar(255),
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT payslips_uniq_slip UNIQUE (run_id, user_id)
);

CREATE TABLE public.sch_clinic_visits (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    visit_at timestamptz NOT NULL,
    complaint varchar(255) NOT NULL,
    action_note varchar(255),
    sent_home boolean DEFAULT false NOT NULL,
    recorded_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_books (
    id bigint GENERATED ALWAYS AS IDENTITY,
    title varchar(190) NOT NULL,
    author varchar(150),
    isbn varchar(20),
    category varchar(80),
    copies smallint DEFAULT 1 NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_book_loans (
    id bigint GENERATED ALWAYS AS IDENTITY,
    book_id bigint NOT NULL,
    student_id bigint NOT NULL,
    loaned_at date NOT NULL,
    due_date date NOT NULL,
    returned_at date,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_visitors (
    id bigint GENERATED ALWAYS AS IDENTITY,
    full_name varchar(190) NOT NULL,
    national_id varchar(20),
    phone varchar(30),
    purpose varchar(190),
    host_user_id bigint,
    student_id bigint,
    checked_in timestamptz NOT NULL,
    checked_out timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_incidents (
    id bigint GENERATED ALWAYS AS IDENTITY,
    incident_type text DEFAULT 'other' NOT NULL,
    title varchar(190) NOT NULL,
    description text,
    severity text DEFAULT 'low' NOT NULL,
    occurred_at timestamptz NOT NULL,
    status text DEFAULT 'open' NOT NULL,
    reported_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_assets (
    id bigint GENERATED ALWAYS AS IDENTITY,
    asset_code varchar(40) NOT NULL,
    name varchar(190) NOT NULL,
    category varchar(80),
    location varchar(120),
    purchase_date date,
    cost numeric(12,2) DEFAULT 0 NOT NULL,
    status text DEFAULT 'in_use' NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT assets_uniq_code UNIQUE (asset_code)
);

CREATE TABLE public.sch_maintenance (
    id bigint GENERATED ALWAYS AS IDENTITY,
    asset_id bigint,
    title varchar(190) NOT NULL,
    description text,
    location varchar(120),
    priority text DEFAULT 'medium' NOT NULL,
    status text DEFAULT 'open' NOT NULL,
    reported_by bigint,
    assigned_to bigint,
    created_at timestamptz NOT NULL,
    closed_at timestamptz,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_inventory (
    id bigint GENERATED ALWAYS AS IDENTITY,
    sku varchar(40),
    name varchar(190) NOT NULL,
    unit varchar(30),
    qty numeric(12,2) DEFAULT 0 NOT NULL,
    min_qty numeric(12,2) DEFAULT 0 NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT inventory_uniq_sku UNIQUE (sku)
);

CREATE TABLE public.sch_stock_moves (
    id bigint GENERATED ALWAYS AS IDENTITY,
    item_id bigint NOT NULL,
    move_type text NOT NULL,
    qty numeric(12,2) NOT NULL,
    note varchar(255),
    moved_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_kg_logs (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    log_date date NOT NULL,
    mood text,
    meal text,
    nap_minutes smallint,
    activities varchar(255),
    note text,
    recorded_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT kg_logs_uniq_day UNIQUE (student_id, log_date)
);

CREATE TABLE public.sch_student_docs (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    doc_type text DEFAULT 'other' NOT NULL,
    file_name varchar(190) NOT NULL,
    stored_as varchar(190) NOT NULL,
    mime_type varchar(80),
    file_size integer DEFAULT 0 NOT NULL,
    uploaded_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT student_docs_uniq_stored UNIQUE (stored_as)
);

CREATE TABLE public.sch_student_health (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    blood_type varchar(8),
    chronic varchar(255),
    allergies varchar(255),
    special_needs varchar(255),
    notes text,
    updated_by bigint,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT student_health_uniq_student UNIQUE (student_id)
);

CREATE TABLE public.sch_custody_events (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    from_state varchar(20),
    to_state varchar(20) NOT NULL,
    checkpoint text NOT NULL,
    actor_user_id bigint,
    receiver_name varchar(190),
    receiver_relation varchar(60),
    picked_by varchar(24),
    reason varchar(255),
    lat numeric(10,7),
    lng numeric(10,7),
    client_uuid char(36) NOT NULL,
    occurred_at timestamptz NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT custody_events_uniq_client UNIQUE (client_uuid)
);

CREATE TABLE public.sch_alerts (
    id bigint GENERATED ALWAYS AS IDENTITY,
    rule_code varchar(40) NOT NULL,
    severity text DEFAULT 'medium' NOT NULL,
    student_id bigint,
    trip_id bigint,
    title varchar(190) NOT NULL,
    detail varchar(500),
    status text DEFAULT 'open' NOT NULL,
    day_key date NOT NULL,
    opened_at timestamptz NOT NULL,
    ack_by bigint,
    ack_at timestamptz,
    closed_by bigint,
    closed_at timestamptz,
    close_reason varchar(255),
    PRIMARY KEY (id),
    CONSTRAINT alerts_uniq_rule_day UNIQUE (rule_code, student_id, day_key)
);

CREATE TABLE public.sch_student_notes (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    category text DEFAULT 'positive' NOT NULL,
    route text DEFAULT 'none' NOT NULL,
    body varchar(500),
    author_user_id bigint,
    status text DEFAULT 'open' NOT NULL,
    assigned_to bigint,
    opened_at timestamptz,
    closed_by bigint,
    closed_at timestamptz,
    resolution varchar(500),
    escalated_at timestamptz,
    recheck_at timestamptz,
    recheck_result text,
    parent_response text,
    parent_body varchar(500),
    responded_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_staff_attendance (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    att_date date NOT NULL,
    status text DEFAULT 'present' NOT NULL,
    method text DEFAULT 'manual' NOT NULL,
    checked_at timestamptz,
    checkout_at timestamptz,
    minutes_late smallint DEFAULT 0 NOT NULL,
    note varchar(255),
    recorded_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT staff_attendance_uniq_day UNIQUE (user_id, att_date)
);

CREATE TABLE public.sch_substitutions (
    id bigint GENERATED ALWAYS AS IDENTITY,
    sub_date date NOT NULL,
    class_id bigint NOT NULL,
    period_no smallint NOT NULL,
    subject_id bigint,
    original_teacher_id bigint,
    substitute_teacher_id bigint,
    reason text DEFAULT 'leave' NOT NULL,
    status text DEFAULT 'proposed' NOT NULL,
    assigned_by bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT substitutions_uniq_slot UNIQUE (sub_date, class_id, period_no)
);

CREATE TABLE public.sch_year_summary (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    year_id bigint NOT NULL,
    works varchar(300),
    avoid varchar(300),
    notes varchar(300),
    author_user_id bigint,
    approved_by bigint,
    approved_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT year_summary_uniq_year UNIQUE (student_id, year_id)
);

CREATE TABLE public.sch_medications (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    med_name varchar(150) NOT NULL,
    dose varchar(60) NOT NULL,
    times_csv varchar(120) NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    note varchar(255),
    file_stored varchar(190),
    status text DEFAULT 'pending' NOT NULL,
    created_by bigint,
    approved_by bigint,
    approved_at timestamptz,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_med_doses (
    id bigint GENERATED ALWAYS AS IDENTITY,
    med_id bigint NOT NULL,
    due_date date NOT NULL,
    due_time varchar(5) NOT NULL,
    given_at timestamptz,
    given_by bigint,
    note varchar(255),
    PRIMARY KEY (id),
    CONSTRAINT med_doses_uniq_dose UNIQUE (med_id, due_date, due_time)
);

CREATE TABLE public.sch_student_leaves (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    reason_code text DEFAULT 'sick' NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    days smallint DEFAULT 1 NOT NULL,
    note varchar(255),
    file_stored varchar(190),
    status text DEFAULT 'pending' NOT NULL,
    requested_by bigint,
    decided_by bigint,
    decided_at timestamptz,
    decision_note varchar(255),
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_content (
    id bigint GENERATED ALWAYS AS IDENTITY,
    content_type text DEFAULT 'book' NOT NULL,
    title varchar(190) NOT NULL,
    description varchar(500),
    stage text DEFAULT 'primary' NOT NULL,
    grade_level varchar(40),
    subject_id bigint,
    lesson_ref varchar(120),
    media_url varchar(500),
    file_stored varchar(190),
    game_code text,
    status text DEFAULT 'draft' NOT NULL,
    opens integer DEFAULT 0 NOT NULL,
    review_at date,
    created_by bigint,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_content_opens (
    id bigint GENERATED ALWAYS AS IDENTITY,
    content_id bigint NOT NULL,
    student_id bigint NOT NULL,
    score smallint,
    seconds integer DEFAULT 0 NOT NULL,
    opened_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_homework (
    id bigint GENERATED ALWAYS AS IDENTITY,
    class_id bigint NOT NULL,
    subject_id bigint,
    teacher_id bigint,
    title varchar(190) NOT NULL,
    body text,
    answer_type text DEFAULT 'text' NOT NULL,
    file_stored varchar(190),
    content_id bigint,
    week_key varchar(10) NOT NULL,
    due_date date NOT NULL,
    taught_on date,
    status text DEFAULT 'open' NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_homework_subs (
    id bigint GENERATED ALWAYS AS IDENTITY,
    homework_id bigint NOT NULL,
    student_id bigint NOT NULL,
    answer_text text,
    file_stored varchar(190),
    submitted_at timestamptz NOT NULL,
    score numeric(5,2),
    feedback varchar(500),
    graded_by bigint,
    graded_at timestamptz,
    PRIMARY KEY (id),
    CONSTRAINT homework_subs_uniq_sub UNIQUE (homework_id, student_id)
);

CREATE TABLE public.sch_user_perms (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    section varchar(40) NOT NULL,
    can_view boolean DEFAULT true NOT NULL,
    can_edit boolean DEFAULT false NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT user_perms_uniq_perm UNIQUE (user_id, section)
);

CREATE TABLE public.sch_user_scope (
    user_id bigint NOT NULL,
    scope text DEFAULT 'all' NOT NULL,
    expires_at date,
    updated_by bigint,
    updated_at timestamptz,
    PRIMARY KEY (user_id)
);

CREATE TABLE public.sch_perm_log (
    id bigint GENERATED ALWAYS AS IDENTITY,
    target_user bigint NOT NULL,
    actor_user bigint,
    granted varchar(500),
    revoked varchar(500),
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE public.sch_watchlist (
    id bigint GENERATED ALWAYS AS IDENTITY,
    user_id bigint NOT NULL,
    student_id bigint NOT NULL,
    notify boolean DEFAULT false NOT NULL,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT watchlist_uniq_watch UNIQUE (user_id, student_id)
);

CREATE TABLE public.sch_flow_marks (
    id bigint GENERATED ALWAYS AS IDENTITY,
    flow varchar(24) NOT NULL,
    entity_id bigint DEFAULT 0 NOT NULL,
    step varchar(32) NOT NULL,
    state text DEFAULT 'done' NOT NULL,
    user_id bigint,
    created_at timestamptz NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT flow_marks_uniq_mark UNIQUE (flow, entity_id, step)
);

CREATE TABLE public.sch_certificates (
    id bigint GENERATED ALWAYS AS IDENTITY,
    student_id bigint NOT NULL,
    year_id bigint NOT NULL,
    type varchar(24) NOT NULL,
    template varchar(16) DEFAULT 'royal' NOT NULL,
    title varchar(120) NOT NULL,
    reason varchar(255),
    serial varchar(32) NOT NULL,
    issued_by bigint,
    issued_at timestamptz NOT NULL,
    status varchar(12) DEFAULT 'valid' NOT NULL,
    revoke_reason varchar(255),
    revoked_by bigint,
    revoked_at timestamptz,
    grade_snapshot varchar(40),
    section_snapshot varchar(40),
    name_snapshot varchar(160),
    created_at timestamptz DEFAULT now() NOT NULL,
    updated_at timestamptz DEFAULT now() NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT certificates_uniq_serial UNIQUE (serial)
);

-- الفهارس
CREATE INDEX students_idx_custody ON public.sch_students (custody_state, custody_at);
CREATE INDEX students_idx_status ON public.sch_students (status);
CREATE INDEX students_idx_grade ON public.sch_students (grade_level, section);
CREATE INDEX students_idx_family ON public.sch_students (family_name);
CREATE INDEX pickup_delegations_idx_student ON public.sch_pickup_delegations (student_id, valid_until);
CREATE INDEX pickup_delegations_idx_status ON public.sch_pickup_delegations (status);
CREATE INDEX guardian_student_idx_student ON public.sch_guardian_student (student_id);
CREATE INDEX refresh_tokens_idx_user ON public.sch_refresh_tokens (user_id, revoked_at);
CREATE INDEX audit_log_idx_object ON public.sch_audit_log (object_type, object_id);
CREATE INDEX audit_log_idx_created ON public.sch_audit_log (created_at);
CREATE INDEX employees_idx_status ON public.sch_employees (status);
CREATE INDEX academic_years_idx_current ON public.sch_academic_years (is_current);
CREATE INDEX classes_idx_year ON public.sch_classes (year_id);
CREATE INDEX classes_idx_stage ON public.sch_classes (stage);
CREATE INDEX enrollments_idx_class ON public.sch_enrollments (class_id, status);
CREATE INDEX guardians_idx_phone ON public.sch_guardians (phone);
CREATE INDEX buses_idx_driver ON public.sch_buses (driver_user_id);
CREATE INDEX buses_idx_status ON public.sch_buses (status);
CREATE INDEX routes_idx_bus ON public.sch_routes (bus_id);
CREATE INDEX route_stops_idx_route ON public.sch_route_stops (route_id, sequence);
CREATE INDEX transport_subs_idx_route ON public.sch_transport_subs (route_id, status);
CREATE INDEX trips_idx_status ON public.sch_trips (status, trip_date);
CREATE INDEX trips_idx_driver ON public.sch_trips (driver_user_id, trip_date);
CREATE INDEX trip_events_idx_trip ON public.sch_trip_events (trip_id, type);
CREATE INDEX trip_events_idx_student ON public.sch_trip_events (student_id, occurred_at);
CREATE INDEX vehicle_positions_idx_trip ON public.sch_vehicle_positions (trip_id, recorded_at);
CREATE INDEX attendance_idx_class_date ON public.sch_attendance (class_id, att_date);
CREATE INDEX attendance_idx_status ON public.sch_attendance (status, att_date);
CREATE INDEX subjects_idx_stage ON public.sch_subjects (stage);
CREATE INDEX class_subjects_idx_teacher ON public.sch_class_subjects (teacher_user_id);
CREATE INDEX timetable_idx_teacher_slot ON public.sch_timetable (teacher_user_id, day_of_week, period_no);
CREATE INDEX assignments_idx_class ON public.sch_assignments (class_id, due_date);
CREATE INDEX exams_idx_class ON public.sch_exams (class_id, exam_date);
CREATE INDEX exam_results_idx_student ON public.sch_exam_results (student_id);
CREATE INDEX fee_plans_idx_year ON public.sch_fee_plans (year_id);
CREATE INDEX invoices_idx_student ON public.sch_invoices (student_id, year_id);
CREATE INDEX invoices_idx_status ON public.sch_invoices (status, due_date);
CREATE INDEX invoices_idx_year ON public.sch_invoices (year_id, status);
CREATE INDEX installments_idx_due ON public.sch_installments (due_date, status);
CREATE INDEX payments_idx_invoice ON public.sch_payments (invoice_id);
CREATE INDEX payments_idx_paid ON public.sch_payments (paid_at);
CREATE INDEX messages_idx_created ON public.sch_messages (created_at);
CREATE INDEX notifications_idx_user ON public.sch_notifications (user_id, read_at);
CREATE INDEX notifications_idx_created ON public.sch_notifications (created_at);
CREATE INDEX deliveries_idx_status ON public.sch_deliveries (status, attempts);
CREATE INDEX deliveries_idx_notif ON public.sch_deliveries (notification_id);
CREATE INDEX pickup_calls_idx_status ON public.sch_pickup_calls (status, created_at);
CREATE INDEX pickup_calls_idx_student ON public.sch_pickup_calls (student_id, status);
CREATE INDEX push_subs_idx_user ON public.sch_push_subs (user_id, status);
CREATE INDEX accounts_idx_type ON public.sch_accounts (type, status);
CREATE INDEX journal_entries_idx_date ON public.sch_journal_entries (entry_date, status);
CREATE INDEX journal_entries_idx_ref ON public.sch_journal_entries (ref_type, ref_id);
CREATE INDEX journal_lines_idx_entry ON public.sch_journal_lines (entry_id);
CREATE INDEX journal_lines_idx_account ON public.sch_journal_lines (account_id);
CREATE INDEX contracts_idx_user ON public.sch_contracts (user_id, status);
CREATE INDEX contracts_idx_end ON public.sch_contracts (end_date, status);
CREATE INDEX leaves_idx_user ON public.sch_leaves (user_id, status);
CREATE INDEX leaves_idx_dates ON public.sch_leaves (start_date, end_date);
CREATE INDEX payslips_idx_user ON public.sch_payslips (user_id);
CREATE INDEX clinic_visits_idx_student ON public.sch_clinic_visits (student_id, visit_at);
CREATE INDEX books_idx_title ON public.sch_books (title);
CREATE INDEX book_loans_idx_book ON public.sch_book_loans (book_id, returned_at);
CREATE INDEX book_loans_idx_due ON public.sch_book_loans (due_date, returned_at);
CREATE INDEX visitors_idx_in ON public.sch_visitors (checked_in, checked_out);
CREATE INDEX incidents_idx_status ON public.sch_incidents (status, occurred_at);
CREATE INDEX assets_idx_status ON public.sch_assets (status);
CREATE INDEX maintenance_idx_status ON public.sch_maintenance (status, priority);
CREATE INDEX inventory_idx_name ON public.sch_inventory (name);
CREATE INDEX stock_moves_idx_item ON public.sch_stock_moves (item_id, created_at);
CREATE INDEX kg_logs_idx_date ON public.sch_kg_logs (log_date);
CREATE INDEX student_docs_idx_student ON public.sch_student_docs (student_id, doc_type);
CREATE INDEX custody_events_idx_student ON public.sch_custody_events (student_id, occurred_at);
CREATE INDEX custody_events_idx_day ON public.sch_custody_events (occurred_at);
CREATE INDEX custody_events_idx_checkpoint ON public.sch_custody_events (checkpoint, occurred_at);
CREATE INDEX alerts_idx_status ON public.sch_alerts (status, severity, opened_at);
CREATE INDEX student_notes_idx_student ON public.sch_student_notes (student_id, created_at);
CREATE INDEX student_notes_idx_queue ON public.sch_student_notes (route, status, created_at);
CREATE INDEX student_notes_idx_recheck ON public.sch_student_notes (recheck_at, recheck_result);
CREATE INDEX staff_attendance_idx_date ON public.sch_staff_attendance (att_date, status);
CREATE INDEX substitutions_idx_sub ON public.sch_substitutions (substitute_teacher_id, sub_date);
CREATE INDEX substitutions_idx_status ON public.sch_substitutions (status, sub_date);
CREATE INDEX year_summary_idx_year ON public.sch_year_summary (year_id, approved_at);
CREATE INDEX medications_idx_student ON public.sch_medications (student_id, status);
CREATE INDEX medications_idx_window ON public.sch_medications (status, start_date, end_date);
CREATE INDEX med_doses_idx_due ON public.sch_med_doses (due_date, given_at);
CREATE INDEX student_leaves_idx_student ON public.sch_student_leaves (student_id, status);
CREATE INDEX student_leaves_idx_window ON public.sch_student_leaves (status, start_date, end_date);
CREATE INDEX content_idx_browse ON public.sch_content (stage, status, content_type);
CREATE INDEX content_idx_subject ON public.sch_content (subject_id, status);
CREATE INDEX content_idx_review ON public.sch_content (review_at, status);
CREATE INDEX content_opens_idx_content ON public.sch_content_opens (content_id, opened_at);
CREATE INDEX content_opens_idx_student ON public.sch_content_opens (student_id, opened_at);
CREATE INDEX homework_idx_class ON public.sch_homework (class_id, week_key);
CREATE INDEX homework_idx_due ON public.sch_homework (due_date, status);
CREATE INDEX homework_subs_idx_student ON public.sch_homework_subs (student_id, submitted_at);
CREATE INDEX user_scope_idx_expiry ON public.sch_user_scope (expires_at);
CREATE INDEX perm_log_idx_target ON public.sch_perm_log (target_user, id);
CREATE INDEX watchlist_idx_student ON public.sch_watchlist (student_id, notify);
CREATE INDEX certificates_idx_student ON public.sch_certificates (student_id, id);
CREATE INDEX certificates_idx_year ON public.sch_certificates (year_id, type);
CREATE INDEX certificates_idx_status ON public.sch_certificates (status);

-- قيود القيم المحصورة (ENUM سابقًا)
ALTER TABLE public.sch_students ADD CONSTRAINT students_id_type_chk CHECK (id_type IN ('national','iqama','visitor'));
ALTER TABLE public.sch_students ADD CONSTRAINT students_gender_chk CHECK (gender IN ('male','female'));
ALTER TABLE public.sch_students ADD CONSTRAINT students_custody_state_chk CHECK (custody_state IN ('home','on_bus','at_school','left_early'));
ALTER TABLE public.sch_students ADD CONSTRAINT students_status_chk CHECK (status IN ('active','transferred','withdrawn','graduated'));
ALTER TABLE public.sch_employees ADD CONSTRAINT employees_stage_scope_chk CHECK (stage_scope IN ('none','early','middle','high','all'));
ALTER TABLE public.sch_employees ADD CONSTRAINT employees_status_chk CHECK (status IN ('active','suspended','left'));
ALTER TABLE public.sch_academic_years ADD CONSTRAINT academic_years_tt_status_chk CHECK (tt_status IN ('draft','pending','published'));
ALTER TABLE public.sch_enrollments ADD CONSTRAINT enrollments_status_chk CHECK (status IN ('active','transferred','withdrawn'));
ALTER TABLE public.sch_guardians ADD CONSTRAINT guardians_id_type_chk CHECK (id_type IN ('national','iqama','visitor'));
ALTER TABLE public.sch_guardians ADD CONSTRAINT guardians_status_chk CHECK (status IN ('active','suspended'));
ALTER TABLE public.sch_buses ADD CONSTRAINT buses_status_chk CHECK (status IN ('active','maintenance','retired'));
ALTER TABLE public.sch_routes ADD CONSTRAINT routes_direction_chk CHECK (direction IN ('morning','afternoon','both'));
ALTER TABLE public.sch_routes ADD CONSTRAINT routes_status_chk CHECK (status IN ('active','inactive'));
ALTER TABLE public.sch_transport_subs ADD CONSTRAINT transport_subs_status_chk CHECK (status IN ('active','suspended','ended'));
ALTER TABLE public.sch_trips ADD CONSTRAINT trips_direction_chk CHECK (direction IN ('morning','afternoon'));
ALTER TABLE public.sch_trips ADD CONSTRAINT trips_status_chk CHECK (status IN ('planned','running','completed','cancelled'));
ALTER TABLE public.sch_trip_events ADD CONSTRAINT trip_events_type_chk CHECK (type IN ('board','alight','absent','handover'));
ALTER TABLE public.sch_attendance ADD CONSTRAINT attendance_status_chk CHECK (status IN ('present','absent','late','excused'));
ALTER TABLE public.sch_attendance ADD CONSTRAINT attendance_method_chk CHECK (method IN ('qr','manual','transport'));
ALTER TABLE public.sch_exams ADD CONSTRAINT exams_exam_type_chk CHECK (exam_type IN ('quiz','midterm','final','participation'));
ALTER TABLE public.sch_invoices ADD CONSTRAINT invoices_pay_mode_chk CHECK (pay_mode IN ('full','school','bnpl'));
ALTER TABLE public.sch_invoices ADD CONSTRAINT invoices_status_chk CHECK (status IN ('open','partial','paid','void'));
ALTER TABLE public.sch_installments ADD CONSTRAINT installments_status_chk CHECK (status IN ('open','partial','paid'));
ALTER TABLE public.sch_payments ADD CONSTRAINT payments_method_chk CHECK (method IN ('cash','transfer','pos','online'));
ALTER TABLE public.sch_messages ADD CONSTRAINT messages_audience_chk CHECK (audience IN ('all_guardians','class','student','staff'));
ALTER TABLE public.sch_deliveries ADD CONSTRAINT deliveries_status_chk CHECK (status IN ('queued','sent','failed','skipped'));
ALTER TABLE public.sch_pickup_calls ADD CONSTRAINT pickup_calls_status_chk CHECK (status IN ('open','onway','done','cancelled'));
ALTER TABLE public.sch_push_subs ADD CONSTRAINT push_subs_status_chk CHECK (status IN ('active','stale'));
ALTER TABLE public.sch_accounts ADD CONSTRAINT accounts_type_chk CHECK (type IN ('asset','liability','equity','revenue','expense'));
ALTER TABLE public.sch_accounts ADD CONSTRAINT accounts_status_chk CHECK (status IN ('active','archived'));
ALTER TABLE public.sch_journal_entries ADD CONSTRAINT journal_entries_status_chk CHECK (status IN ('draft','posted'));
ALTER TABLE public.sch_contracts ADD CONSTRAINT contracts_contract_type_chk CHECK (contract_type IN ('full_time','part_time','temporary'));
ALTER TABLE public.sch_contracts ADD CONSTRAINT contracts_status_chk CHECK (status IN ('active','expired','terminated'));
ALTER TABLE public.sch_leaves ADD CONSTRAINT leaves_leave_type_chk CHECK (leave_type IN ('annual','sick','emergency','unpaid'));
ALTER TABLE public.sch_leaves ADD CONSTRAINT leaves_status_chk CHECK (status IN ('pending','approved','rejected'));
ALTER TABLE public.sch_payroll_runs ADD CONSTRAINT payroll_runs_status_chk CHECK (status IN ('draft','posted'));
ALTER TABLE public.sch_incidents ADD CONSTRAINT incidents_incident_type_chk CHECK (incident_type IN ('safety','injury','drill','security','other'));
ALTER TABLE public.sch_incidents ADD CONSTRAINT incidents_severity_chk CHECK (severity IN ('low','medium','high'));
ALTER TABLE public.sch_incidents ADD CONSTRAINT incidents_status_chk CHECK (status IN ('open','closed'));
ALTER TABLE public.sch_assets ADD CONSTRAINT assets_status_chk CHECK (status IN ('in_use','storage','maintenance','disposed'));
ALTER TABLE public.sch_maintenance ADD CONSTRAINT maintenance_priority_chk CHECK (priority IN ('low','medium','high'));
ALTER TABLE public.sch_maintenance ADD CONSTRAINT maintenance_status_chk CHECK (status IN ('open','in_progress','done'));
ALTER TABLE public.sch_stock_moves ADD CONSTRAINT stock_moves_move_type_chk CHECK (move_type IN ('in','out','adjust'));
ALTER TABLE public.sch_kg_logs ADD CONSTRAINT kg_logs_mood_chk CHECK (mood IN ('happy','calm','tired','upset'));
ALTER TABLE public.sch_kg_logs ADD CONSTRAINT kg_logs_meal_chk CHECK (meal IN ('all','most','some','none'));
ALTER TABLE public.sch_student_docs ADD CONSTRAINT student_docs_doc_type_chk CHECK (doc_type IN ('id','birth','transcript','photo','other'));
ALTER TABLE public.sch_custody_events ADD CONSTRAINT custody_events_checkpoint_chk CHECK (checkpoint IN ('bus_board','gate_in','gate_out','early_out','bus_alight','manual','correction'));
ALTER TABLE public.sch_alerts ADD CONSTRAINT alerts_severity_chk CHECK (severity IN ('medium','high','critical'));
ALTER TABLE public.sch_alerts ADD CONSTRAINT alerts_status_chk CHECK (status IN ('open','ack','closed'));
ALTER TABLE public.sch_student_notes ADD CONSTRAINT student_notes_category_chk CHECK (category IN ('positive','participation','followup','health'));
ALTER TABLE public.sch_student_notes ADD CONSTRAINT student_notes_route_chk CHECK (route IN ('none','specialist','clinic'));
ALTER TABLE public.sch_student_notes ADD CONSTRAINT student_notes_status_chk CHECK (status IN ('open','in_progress','closed'));
ALTER TABLE public.sch_student_notes ADD CONSTRAINT student_notes_recheck_result_chk CHECK (recheck_result IN ('improved','same','worse'));
ALTER TABLE public.sch_student_notes ADD CONSTRAINT student_notes_parent_response_chk CHECK (parent_response IN ('knows','unknown','contact_me'));
ALTER TABLE public.sch_staff_attendance ADD CONSTRAINT staff_attendance_status_chk CHECK (status IN ('present','late','absent','leave'));
ALTER TABLE public.sch_staff_attendance ADD CONSTRAINT staff_attendance_method_chk CHECK (method IN ('qr','manual'));
ALTER TABLE public.sch_substitutions ADD CONSTRAINT substitutions_reason_chk CHECK (reason IN ('leave','absence','task'));
ALTER TABLE public.sch_substitutions ADD CONSTRAINT substitutions_status_chk CHECK (status IN ('proposed','assigned','done'));
ALTER TABLE public.sch_medications ADD CONSTRAINT medications_status_chk CHECK (status IN ('pending','active','ended','rejected'));
ALTER TABLE public.sch_student_leaves ADD CONSTRAINT student_leaves_reason_code_chk CHECK (reason_code IN ('sick','appointment','travel','family','social','other'));
ALTER TABLE public.sch_student_leaves ADD CONSTRAINT student_leaves_status_chk CHECK (status IN ('pending','approved','rejected'));
ALTER TABLE public.sch_content ADD CONSTRAINT content_content_type_chk CHECK (content_type IN ('book','video','game','link'));
ALTER TABLE public.sch_content ADD CONSTRAINT content_stage_chk CHECK (stage IN ('kg','primary','middle','high'));
ALTER TABLE public.sch_content ADD CONSTRAINT content_status_chk CHECK (status IN ('draft','published','archived'));
ALTER TABLE public.sch_homework ADD CONSTRAINT homework_answer_type_chk CHECK (answer_type IN ('text','drawing','file','none'));
ALTER TABLE public.sch_homework ADD CONSTRAINT homework_status_chk CHECK (status IN ('open','closed'));
ALTER TABLE public.sch_user_scope ADD CONSTRAINT user_scope_scope_chk CHECK (scope IN ('all','stage','own'));
ALTER TABLE public.sch_flow_marks ADD CONSTRAINT flow_marks_state_chk CHECK (state IN ('done','na'));
