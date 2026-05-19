#!/usr/bin/env php
<?php
// ParlReg — Database Seeder
// Usage: php database/seeds/seed.php
// Inserts: 1 sample event + form schema + FAQs for immediate testing

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

echo "\n ParlReg Database Seeder\n";
echo " " . str_repeat('─', 40) . "\n";

// ─── Check admin exists ───────────────────────────────────────────────────────
$admin = DB::row("SELECT id FROM users WHERE email = 'admin@parliament.local'");
if (!$admin) {
    $hash = password_hash('Admin@ParlReg1', PASSWORD_BCRYPT, ['cost' => 12]);
    $adminId = DB::insert("INSERT INTO users (fullname, email, password_hash, role) VALUES (?,?,?,?)",
        ['System Administrator', 'admin@parliament.local', $hash, 'admin']);
    echo " Created admin user (id=$adminId)\n";
} else {
    $adminId = $admin['id'];
    echo " Admin exists (id=$adminId)\n";
}

// ─── Default email templates ──────────────────────────────────────────────────
$existing = DB::row("SELECT id FROM email_templates WHERE type='confirmation' AND event_id IS NULL");
if (!$existing) {
    DB::run("INSERT INTO email_templates (event_id,type,subject_en,subject_fr,body_en,body_fr) VALUES
        (NULL,'confirmation','Registration Confirmed — {{event_name}}','Inscription confirmée — {{event_name}}',
         'Dear {{participant_name}},\n\nYour registration for {{event_name}} has been received.\n\nReference: {{reference_number}}\nDate: {{event_date}}\nLocation: {{event_location}}\n\nKind regards,\nParliamentary Services',
         'Cher(e) {{participant_name}},\n\nVotre inscription à {{event_name}} a bien été reçue.\n\nRéférence: {{reference_number}}\n\nCordialement,\nServices Parlementaires')");
    DB::run("INSERT INTO email_templates (event_id,type,subject_en,subject_fr,body_en,body_fr) VALUES
        (NULL,'admin_notification','New Registration — {{event_name}}','Nouvelle inscription — {{event_name}}',
         'New registration for {{event_name}}.\n\nParticipant: {{participant_name}}\nEmail: {{participant_email}}\nOrganisation: {{participant_organisation}}\nCountry: {{participant_country}}\nReference: {{reference_number}}\nSubmitted: {{submitted_at}}',
         'Nouvelle inscription pour {{event_name}}.\n\nParticipant: {{participant_name}}\nEmail: {{participant_email}}')");
    echo " Default email templates created\n";
}

// ─── Sample event ─────────────────────────────────────────────────────────────
$existingEvent = DB::row("SELECT id FROM events WHERE slug='inter-parliamentary-forum-2026'");
if (!$existingEvent) {
    $defaultSections = json_encode(['sections' => [
        ['key'=>'hero',          'enabled'=>true,  'locked'=>true,  'order'=>1, 'content_en'=>'','content_fr'=>''],
        ['key'=>'key_info',      'enabled'=>true,  'locked'=>false, 'order'=>2,
         'content_en'=>'<p>The Inter-Parliamentary Forum 2026 brings together Members of Parliament from across Africa to address climate resilience, food security, and democratic governance.</p>',
         'content_fr'=>'<p>Le Forum Interparlementaire 2026 réunit des membres du Parlement d\'Afrique pour aborder la résilience climatique, la sécurité alimentaire et la gouvernance démocratique.</p>'],
        ['key'=>'event_details', 'enabled'=>true,  'locked'=>true,  'order'=>3, 'content_en'=>'','content_fr'=>''],
        ['key'=>'agenda',        'enabled'=>true,  'locked'=>false, 'order'=>4,
         'content_en'=>'<p><strong>Day 1:</strong> Opening Ceremony and Keynote Addresses</p><p><strong>Day 2:</strong> Thematic Working Groups</p><p><strong>Day 3:</strong> Plenary Session and Closing Declaration</p>',
         'content_fr'=>'<p><strong>Jour 1:</strong> Cérémonie d\'ouverture et discours</p><p><strong>Jour 2:</strong> Groupes de travail thématiques</p><p><strong>Jour 3:</strong> Session plénière et déclaration finale</p>'],
        ['key'=>'faqs',          'enabled'=>true,  'locked'=>false, 'order'=>5, 'content_en'=>'','content_fr'=>''],
        ['key'=>'form',          'enabled'=>true,  'locked'=>true,  'order'=>6, 'content_en'=>'','content_fr'=>''],
        ['key'=>'contact',       'enabled'=>true,  'locked'=>true,  'order'=>7, 'content_en'=>'','content_fr'=>''],
    ]]);

    $schema = json_encode([
        ['id'=>'f001','type'=>'header','label'=>['en'=>'Personal Information','fr'=>'Informations personnelles'],'order'=>1],
        ['id'=>'f002','type'=>'text','label'=>['en'=>'Full Name','fr'=>'Nom complet'],'placeholder'=>['en'=>'As on official ID','fr'=>'Tel que sur pièce d\'identité'],'required'=>true,'order'=>2],
        ['id'=>'f003','type'=>'email','label'=>['en'=>'Email Address','fr'=>'Adresse e-mail'],'required'=>true,'order'=>3],
        ['id'=>'f004','type'=>'phone','label'=>['en'=>'Phone Number','fr'=>'Numéro de téléphone'],'placeholder'=>['en'=>'+1 234 567 8900','fr'=>'+1 234 567 8900'],'required'=>false,'order'=>4],
        ['id'=>'f005','type'=>'header','label'=>['en'=>'Professional Details','fr'=>'Détails professionnels'],'order'=>5],
        ['id'=>'f006','type'=>'select','label'=>['en'=>'Title / Role','fr'=>'Titre / Rôle'],'required'=>true,'order'=>6,
         'options'=>[
            ['value'=>'mp',   'label'=>['en'=>'Member of Parliament','fr'=>'Membre du Parlement']],
            ['value'=>'obs',  'label'=>['en'=>'Official Observer',   'fr'=>'Observateur officiel']],
            ['value'=>'dipl', 'label'=>['en'=>'Diplomat',            'fr'=>'Diplomate']],
            ['value'=>'staff','label'=>['en'=>'Parliamentary Staff', 'fr'=>'Personnel parlementaire']],
            ['value'=>'press','label'=>['en'=>'Press / Media',       'fr'=>'Presse / Médias']],
         ]],
        ['id'=>'f007','type'=>'text','label'=>['en'=>'Organisation / Parliament','fr'=>'Organisation / Parlement'],'required'=>true,'order'=>7],
        ['id'=>'f008','type'=>'text','label'=>['en'=>'Country','fr'=>'Pays'],'required'=>true,'order'=>8],
        ['id'=>'f009','type'=>'header','label'=>['en'=>'Documents','fr'=>'Documents'],'order'=>9],
        ['id'=>'f010','type'=>'file','label'=>['en'=>'Passport / ID Scan (optional)','fr'=>'Scan passeport / pièce d\'identité (optionnel)'],'required'=>false,'order'=>10,
         'validation'=>['maxSize'=>5,'acceptedTypes'=>['application/pdf','image/jpeg','image/png']]],
        ['id'=>'f011','type'=>'textarea','label'=>['en'=>'Dietary Requirements / Special Needs','fr'=>'Exigences alimentaires / Besoins spéciaux'],'required'=>false,'order'=>11,
         'placeholder'=>['en'=>'Leave blank if none','fr'=>'Laisser vide si aucun']],
    ]);

    $eventId = DB::insert(
        "INSERT INTO events (slug,name_en,name_fr,date_start,date_end,location_en,location_fr,
                              meta_title_en,meta_title_fr,meta_desc_en,meta_desc_fr,
                              config_json,form_schema_json,status,approval_mode,theme_color,
                              registration_deadline,capacity,created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'published','auto',?,?,?,?)",
        [
            'inter-parliamentary-forum-2026',
            'Inter-Parliamentary Forum 2026',
            'Forum Interparlementaire 2026',
            '2026-09-01 09:00:00',
            '2026-09-03 18:00:00',
            'Parliament House – Main Hall, Accra, Ghana',
            'Parlement – Grande Salle, Accra, Ghana',
            'Inter-Parliamentary Forum 2026 | Register Now',
            'Forum Interparlementaire 2026 | Inscrivez-vous',
            'Register for the 2026 Inter-Parliamentary Forum hosted at Parliament House, Accra.',
            'Inscrivez-vous au Forum Interparlementaire 2026 au Parlement, Accra.',
            $defaultSections,
            $schema,
            '#1B3A6B',
            '2026-08-25 23:59:59',
            300,
            $adminId,
        ]
    );
    echo " Sample event created (id=$eventId, slug=inter-parliamentary-forum-2026)\n";

    // FAQs
    $faqs = [
        ['Who can attend this forum?', 'Qui peut participer à ce forum?',
         'The forum is open to all Members of Parliament, accredited observers, diplomats, and parliamentary staff.',
         'Le forum est ouvert à tous les membres du Parlement, observateurs accrédités, diplomates et personnel parlementaire.'],
        ['Is there a registration fee?', 'Y a-t-il des frais d\'inscription?',
         'No. Registration is completely free for all participants.',
         'Non. L\'inscription est entièrement gratuite pour tous les participants.'],
        ['What documents do I need?', 'Quels documents sont nécessaires?',
         'A valid passport or government-issued ID is required for accreditation at the event.',
         'Un passeport valide ou une pièce d\'identité délivrée par le gouvernement est requis pour l\'accréditation.'],
        ['Will interpretation be available?', 'Une interprétation sera-t-elle disponible?',
         'Yes. Simultaneous interpretation in English and French will be available throughout the forum.',
         'Oui. Une interprétation simultanée en anglais et en français sera disponible pendant tout le forum.'],
    ];
    foreach ($faqs as $i => $faq) {
        DB::run("INSERT INTO faqs (event_id,question_en,question_fr,answer_en,answer_fr,sort_order) VALUES (?,?,?,?,?,?)",
                [$eventId, $faq[0], $faq[1], $faq[2], $faq[3], $i+1]);
    }
    echo " 4 FAQs created\n";

    // Sample registrations
    $sampleRegs = [
        ['Kwame Asante',     'kwame.asante@parliament.gh',    '+233201234567', 'Parliament of Ghana',      'Ghana',         'approved'],
        ['Marie Dupont',     'marie.dupont@assemblee.fr',     '+33612345678',  'Assemblée Nationale',      'France',        'approved'],
        ['John Mwangi',      'j.mwangi@parliament.ke',        '+254712345678', 'National Assembly Kenya',  'Kenya',         'pending'],
        ['Amina Diallo',     'amina.diallo@assemblee.sn',     '+221701234567', 'Assemblée Nationale',      'Senegal',       'pending'],
        ['Carlos Ferreira',  'c.ferreira@parliament.mz',      '',              'Parliament of Mozambique', 'Mozambique',    'approved'],
    ];
    foreach ($sampleRegs as $r) {
        $ref = 'PARL-' . strtoupper(substr(md5(uniqid('',true)),0,8));
        DB::run("INSERT INTO registrations (event_id,fullname,email,phone,organisation,country,status,reference_no,data_json,consent_given,consent_ts,consent_ip)
                 VALUES (?,?,?,?,?,?,?,?,?,1,NOW(),'127.0.0.1')",
                [$eventId,$r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$ref,
                 json_encode(['Title/Role'=>'mp','Dietary Requirements'=>''])]);
    }
    echo " 5 sample registrations created\n";
} else {
    echo " Sample event already exists\n";
}

echo "\n Seeding complete.\n";
echo " Portal URL: " . (getenv('APP_URL') ?: 'http://localhost:8000') . "/events/inter-parliamentary-forum-2026\n";
echo " Admin URL:  " . (getenv('APP_URL') ?: 'http://localhost:8000') . "/admin\n\n";
