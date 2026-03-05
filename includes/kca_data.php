<?php
// ============================================================
// KCA CHART — Real KCA University Data Constants
// Schools, Departments, Courses, Clubs
// ============================================================

define('KCA_SCHOOLS', [
    'School of Technology' => [
        'short'       => 'SoT',
        'departments' => [
            'Department of Software Development & Information Systems',
            'Department of Networks & Applied Computing',
        ],
        'courses' => [
            'PhD in Information Systems',
            'MSc in Information Systems Management',
            'MSc in Data Science',
            'MSc in Data Analytics',
            'BSc in Software Development',
            'BSc in Information Technology',
            'BSc in Data Science',
            'BSc in Information Security & Forensics',
            'BSc in Applied Computing',
            'BSc in Gaming & Animation Technology',
            'Bachelor of Business Information Technology (BBIT)',
            'Diploma in Information Technology',
            'Diploma in Business Information Technology',
            'Certificate in Information Technology',
            'Certificate in Business Information Technology',
        ],
    ],
    'School of Business' => [
        'short'       => 'SoB',
        'departments' => [
            'Department of Business Administration',
            'Department of Accounting, Finance & Economics',
            'Department of Economics & Statistics',
        ],
        'courses' => [
            'PhD in Business Management',
            'PhD in Finance',
            'MBA (Corporate Management)',
            'MSc in Supply Chain Management',
            'MSc in Commerce',
            'MSc in Knowledge Management & Innovation',
            'MSc in Development Finance',
            'Bachelor of Commerce',
            'Bachelor of International Business Management',
            'Bachelor of Procurement & Logistics',
            'Bachelor of Public Management',
            'BSc in Actuarial Science',
            'BSc in Forensic Accounting',
            'BSc in Economics & Statistics',
            'Diploma in Business Management',
            'Diploma in Procurement & Logistics',
            'Diploma in Public Management',
            'Certificate in Business Management',
            'Certificate in Procurement & Logistics',
            'ACCA (UK)',
            'CPA Kenya',
            'CPSP-K (Procurement)',
            'Certified Secretaries (CS)',
            'CIMA',
        ],
    ],
    'School of Education, Arts & Social Sciences' => [
        'short'       => 'SEASS',
        'departments' => [
            'Department of Educational & Psychological Studies',
            'Department of Performing Arts, Film & Media',
            'Department of Social Sciences',
        ],
        'courses' => [
            'Master of Education (Leadership & Management)',
            'Master of Education (Administration, Curriculum & Policy)',
            'Master of Arts in Counselling Psychology',
            'Postgraduate Diploma in Education',
            'BA in Journalism & Digital Media',
            'BA in Performing Arts & Film',
            'BA in Criminology',
            'BA in Counselling Psychology',
            'BA in Early Childhood Education',
            'BA in Economics & Business Studies',
            'Bachelor of Education (Arts)',
            'Diploma in Journalism & Digital Media',
            'Diploma in Criminology & Criminal Justice',
            'Diploma in Film Technology',
            'Diploma in Counselling Psychology',
            'Diploma in Education',
            'Certificate in Film Technology',
            'Certificate in Counselling Psychology',
        ],
    ],
    'Board of Postgraduate Studies' => [
        'short'       => 'PostGrad',
        'departments' => ['Board of Postgraduate Studies'],
        'courses' => [
            'PhD (Cross-disciplinary research)',
            'Masters by Research',
        ],
    ],
    'KCA PTTI (Professional & Technical Training Institute)' => [
        'short'       => 'PTTI',
        'departments' => ['Professional & Technical Training'],
        'courses' => [
            'ICDL',
            'CISCO Networking',
            'Short Professional Courses',
            'Office Skills Training',
        ],
    ],
]);

define('KCA_YEAR_OPTIONS', [
    1 => 'Year 1',
    2 => 'Year 2',
    3 => 'Year 3',
    4 => 'Year 4',
    0 => 'Postgraduate / Alumni',
]);

define('KCA_CAMPUSES', [
    'Town Campus'      => 'Town Campus — Nairobi CBD',
    'Western Campus'   => 'Western Campus — Kisumu',
    'Kitengela Campus' => 'Kitengela Campus',
    'Online'           => 'Online / Distance Learning',
]);

define('KCA_CLUBS', [
    'KCAU Tech Club',
    'KCA Cybersecurity Club',
    'Google Developer Student Club (GDSC)',
    'Microsoft Student Learn Community',
    'Drama Club',
    'Music Club',
    'Wildlife Club',
    'Journalism Club',
    'Peer Counselling Club',
    'Chama Cha Kiswahili',
    'Actuarial Students Association of Kenya (ASSK) — KCA Chapter',
    'KCA Debate Society',
    'KCA Sports & Athletics',
    'KCA Entrepreneurship Hub',
    'SAKU — Students Association of KCA University',
]);

// Helper: get all courses as flat array
function getAllKcaCourses(): array {
    $all = [];
    foreach (KCA_SCHOOLS as $school => $data) {
        foreach ($data['courses'] as $course) {
            $all[$school][] = $course;
        }
    }
    return $all;
}

// Allowed email domains for KCA
define('KCA_EMAIL_DOMAINS', ['students.kcau.ac.ke', 'kcau.ac.ke', 'kca.ac.ke']);
