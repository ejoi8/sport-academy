-- Demo data verification — run against the football_academy MySQL database after seeding.
-- php artisan migrate:fresh --force && php artisan db:seed --class="Database\Seeders\DemoSeeder" --force

-- 1. CATALOG — every program & offering, with enrolment counts
SELECT p.name AS program, p.is_active, o.period, o.schedule_type, o.weekday,
       o.specific_date, TIME_FORMAT(o.start_time,'%H:%i') AS start, o.capacity, o.is_open,
       (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = o.id) AS enrolled
FROM offerings o JOIN programs p ON p.id = o.program_id
ORDER BY o.period, p.name, o.start_time;

-- 2. ENROLMENT STATUS SPREAD
SELECT status, COUNT(*) AS n FROM enrollments GROUP BY status;

-- 3. CREDIT STATE PER ENROLMENT — mirrors the 3-state badge
SELECT s.name AS student, p.name AS program, o.period,
       e.sessions_included AS paid, COALESCE(a.used,0) AS used,
       CASE
         WHEN COALESCE(a.used,0) > e.sessions_included THEN CONCAT('OVER +', a.used - e.sessions_included)
         WHEN COALESCE(a.used,0) = e.sessions_included AND e.sessions_included > 0 THEN 'PAID UP'
         ELSE CONCAT('IN PROGRESS (', e.sessions_included - COALESCE(a.used,0), ' left)')
       END AS state
FROM enrollments e
JOIN students s  ON s.id = e.student_id
JOIN offerings o ON o.id = e.offering_id
JOIN programs p  ON p.id = o.program_id
LEFT JOIN (SELECT enrollment_id, COUNT(*) AS used FROM attendances
           WHERE status IN ('present','late','absent')
           GROUP BY enrollment_id) a ON a.enrollment_id = e.id
WHERE e.status IN ('active','pending','overdue')
ORDER BY state, s.name;

-- 4. CARRY-OVER — live leftovers (the "+N carried" chip); expired excluded
SELECT s.name, SUM(GREATEST(e.sessions_included - COALESCE(a.used,0), 0)) AS carried
FROM enrollments e
JOIN students s ON s.id = e.student_id
LEFT JOIN (SELECT enrollment_id, COUNT(*) AS used FROM attendances
           WHERE status IN ('present','late','absent') GROUP BY enrollment_id) a
       ON a.enrollment_id = e.id
WHERE e.status IN ('active','pending','overdue')
  AND (e.credits_expire_at IS NULL OR e.credits_expire_at >= CURDATE())
GROUP BY s.id, s.name HAVING carried > 0 ORDER BY carried DESC;

-- 5. ATTENDANCE MIX — types × statuses; walk-in fees only when attended
SELECT participant_type, status, COUNT(*) AS n,
       SUM(COALESCE(walk_in_fee_sen,0))/100 AS walk_in_fees_rm
FROM attendances GROUP BY participant_type, status ORDER BY participant_type, status;

-- 6. OFF-SCHEDULE SESSIONS — recorded on a day the class doesn't normally run
SELECT p.name, o.weekday AS scheduled_iso, ts.session_date,
       WEEKDAY(ts.session_date)+1 AS actual_iso
FROM training_sessions ts
JOIN offerings o ON o.id = ts.offering_id
JOIN programs p ON p.id = o.program_id
WHERE o.schedule_type = 'recurring' AND WEEKDAY(ts.session_date)+1 <> o.weekday;

-- 7. CLOSED-FOR-REGISTRATION classes that still run
SELECT p.name, o.period, o.is_open,
       (SELECT COUNT(*) FROM training_sessions t WHERE t.offering_id = o.id) AS sessions_recorded,
       (SELECT COUNT(*) FROM enrollments e WHERE e.offering_id = o.id) AS enrolled
FROM offerings o JOIN programs p ON p.id = o.program_id WHERE o.is_open = 0;

-- 8. OVER-DELIVERED — matches the new Enrolments filter
SELECT s.name, e.sessions_included AS paid, COUNT(a.id) AS used
FROM enrollments e
JOIN students s ON s.id = e.student_id
JOIN attendances a ON a.enrollment_id = e.id AND a.status IN ('present','late','absent')
GROUP BY e.id, s.name, e.sessions_included
HAVING used > e.sessions_included;

-- 9. LIFETIME SUMMARY per student — purchased · attended · owed · over (never netted)
SELECT s.name,
       SUM(e.sessions_included) AS purchased, SUM(COALESCE(a.used,0)) AS attended,
       SUM(GREATEST(e.sessions_included - COALESCE(a.used,0),0)) AS owed,
       SUM(GREATEST(COALESCE(a.used,0) - e.sessions_included,0)) AS over
FROM enrollments e
JOIN students s ON s.id = e.student_id
LEFT JOIN (SELECT enrollment_id, COUNT(*) AS used FROM attendances
           WHERE status IN ('present','late','absent') GROUP BY enrollment_id) a
       ON a.enrollment_id = e.id
WHERE e.status IN ('active','pending','overdue')
GROUP BY s.id, s.name HAVING owed > 0 OR over > 0 ORDER BY over DESC, owed DESC;

-- 10. INTEGRITY — first two must be 0; third informational (expected >= 1)
SELECT 'scores_on_absent_or_excused' AS check_name, COUNT(*) AS n
FROM assessment_scores sc JOIN attendances a ON a.id = sc.attendance_id
WHERE a.status IN ('absent','excused')
UNION ALL
SELECT 'fee_on_absent_walkin', COUNT(*) FROM attendances
WHERE participant_type='walk_in' AND status IN ('absent','excused') AND walk_in_fee_sen IS NOT NULL
UNION ALL
SELECT 'cancelled_enrolment_keeps_history (expected >= 1)', COUNT(*) FROM enrollments e
JOIN attendances a ON a.enrollment_id = e.id WHERE e.status='cancelled';
