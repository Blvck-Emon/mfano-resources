-- ============================================================================
-- Mfano Bora Resources Portal - Seed Data (SQLite)
-- Refactored from the Postgres seed.sql:
--   * TRUE/FALSE literals -> 1/0
--   * SELECT setval(...) sequence resets -> removed (AUTOINCREMENT needs none)
--   * INSERT -> INSERT OR IGNORE, so re-running this file is safe/idempotent
-- ============================================================================

PRAGMA foreign_keys = ON;

-- 1. Seed Core Categories (Derived from the 10 recommended sections)
INSERT OR IGNORE INTO categories (id, name, slug, description) VALUES
(1, 'Attachment & Internship Resources', 'attachment-internship', 'Resources for students preparing for industrial attachment and workplace opportunities.'),
(2, 'Careers & Professional Development', 'careers', 'Resources to prepare for employment, professional development, and career readiness.'),
(3, 'ICT & Digital Skills', 'ict-digital-skills', 'Technology-focused resources, basic computer skills, and cybersecurity awareness.'),
(4, 'Mfano Africa ICT Hub', 'ict-hub', 'Course catalogues, training programmes, and pathways for the ICT Hub.'),
(5, 'Transport & Logistics', 'transport-logistics', 'Information related to transport, logistics, road safety, and mobility.'),
(6, 'Road Safety', 'road-safety', 'Educational resources promoting safer roads, defensive driving, and transport safety.'),
(7, 'Awards & Events', 'awards-events', 'Resources for the East Africa Transport, Logistics & Road Safety Awards and events.'),
(8, 'Company Resources', 'company-resources', 'Official Mfano Bora Africa company profiles, reports, and corporate brochures.'),
(9, 'Forms & Templates', 'forms-templates', 'Commonly requested application, registration, and feedback forms.'),
(10, 'Reports, Publications & Research', 'reports-publications', 'Industry reports, research publications, and Mfano Bora insights.');

-- 2. Seed Sub-Categories (one primary sub-category per main category for staging)
INSERT OR IGNORE INTO sub_categories (id, category_id, name, slug, description) VALUES
(1, 1, 'Attachment Guides & Forms', 'attachment-guides', 'Application forms, logbook guides, and checklists.'),
(2, 2, 'Career Guides & Prep', 'career-prep', 'CV writing, interview prep, and professional ethics.'),
(3, 3, 'Digital Skills & Safety', 'digital-skills', 'Basic computer skills, internet safety, and AI awareness.'),
(4, 4, 'ICT Training Catalogues', 'ict-catalogues', 'Course catalogues and registration materials for the ICT Hub.'),
(5, 5, 'Logistics & Fleet Management', 'logistics-management', 'Operations guides, industry overviews, and best practices.'),
(6, 6, 'Road Safety Awareness', 'road-safety-awareness', 'Awareness guides, pedestrian safety, and campaign materials.'),
(7, 7, 'Awards Documentation', 'awards-docs', 'Nomination guides, judging criteria, and sponsorship brochures.'),
(8, 8, 'Corporate Publications', 'corporate-pubs', 'Annual reports, CSR reports, and brand profiles.'),
(9, 9, 'Standard Forms', 'standard-forms', 'Blank templates and standard request forms.'),
(10, 10, 'Industry Reports & Insights', 'industry-reports', 'In-depth research covering transport, youth employment, and ICT.');

-- 3. Seed Resources (Format: sub_category_id, title, description, file_url, is_featured)
INSERT OR IGNORE INTO resources (sub_category_id, title, description, file_url, is_featured) VALUES
-- Category 1: Attachment & Internship Resources
(1, 'Attachment Application Form', 'Standard form to apply for industrial attachment opportunities.', 'https://storage.mfanobora.com/docs/attachment-application-form.pdf', 1),
(1, 'Attachment Requirements', 'Requirements for students seeking placement.', 'https://storage.mfanobora.com/docs/attachment-requirements.pdf', 0),
(1, 'Industrial Attachment Guide', 'Comprehensive guidelines for industrial attachment.', 'https://storage.mfanobora.com/docs/industrial-attachment-guide.pdf', 1),
(1, 'Internship Application Guide', 'Step-by-step application information for internship opportunities.', 'https://storage.mfanobora.com/docs/internship-application-guide.pdf', 0),
(1, 'Student Logbook Guide', 'Guidance on properly filling out the student logbook.', 'https://storage.mfanobora.com/docs/student-logbook-guide.pdf', 0),
(1, 'Workplace Readiness Guide', 'Essential skills for workplace readiness and professional conduct.', 'https://storage.mfanobora.com/docs/workplace-readiness-guide.pdf', 1),
(1, 'Attachment Interview Preparation Guide', 'Preparation materials for attachment interviews.', 'https://storage.mfanobora.com/docs/attachment-interview-prep.pdf', 0),
(1, 'Attachment Placement Guide', 'Information on attachment placement processes.', 'https://storage.mfanobora.com/docs/attachment-placement-guide.pdf', 0),
(1, 'Student Attachment Checklist', 'Checklist for students starting their attachment.', 'https://storage.mfanobora.com/docs/student-attachment-checklist.pdf', 0),
(1, 'Supervisor Assessment Guide', 'Guide for supervisors to assess attachment students.', 'https://storage.mfanobora.com/docs/supervisor-assessment-guide.pdf', 0),

-- Category 2: Careers & Professional Development
(2, 'CV Writing Guide', 'Practical information to help students build a professional CV.', 'https://storage.mfanobora.com/docs/cv-writing-guide.pdf', 1),
(2, 'Cover Letter Writing Guide', 'Guidelines for crafting effective cover letters.', 'https://storage.mfanobora.com/docs/cover-letter-guide.pdf', 0),
(2, 'Interview Preparation Guide', 'Comprehensive interview preparation strategies.', 'https://storage.mfanobora.com/docs/interview-prep-guide.pdf', 1),
(2, 'Career Readiness Guide', 'Materials for students and job seekers preparing for careers.', 'https://storage.mfanobora.com/docs/career-readiness-guide.pdf', 0),
(2, 'Professional Ethics Guide', 'Guide on maintaining professional ethics in the workplace.', 'https://storage.mfanobora.com/docs/professional-ethics.pdf', 0),
(2, 'Workplace Communication Guide', 'Best practices for workplace communication.', 'https://storage.mfanobora.com/docs/workplace-communication.pdf', 0),
(2, 'Time Management Guide', 'Effective time management strategies for professionals.', 'https://storage.mfanobora.com/docs/time-management-guide.pdf', 0),
(2, 'Teamwork & Collaboration Guide', 'Guide on effective teamwork and collaboration.', 'https://storage.mfanobora.com/docs/teamwork-guide.pdf', 0),
(2, 'Personal Branding Guide', 'How to build and manage your personal professional brand.', 'https://storage.mfanobora.com/docs/personal-branding.pdf', 0),
(2, 'Job Application Guide', 'Step-by-step guide to the job application process.', 'https://storage.mfanobora.com/docs/job-application-guide.pdf', 0),
(2, 'Graduate Career Guide', 'Specific career guidance for recent graduates.', 'https://storage.mfanobora.com/docs/graduate-career-guide.pdf', 0),
(2, 'Professional Development Guide', 'Materials for ongoing professional development.', 'https://storage.mfanobora.com/docs/professional-development.pdf', 0),

-- Category 3: ICT & Digital Skills
(3, 'Basic Computer Skills Guide', 'Foundational guide for basic computer operation.', 'https://storage.mfanobora.com/docs/basic-computer-skills.pdf', 1),
(3, 'Digital Skills Guide', 'Improve understanding of digital tools and emerging tech.', 'https://storage.mfanobora.com/docs/digital-skills-guide.pdf', 1),
(3, 'Internet Safety Guide', 'Best practices for safe internet usage.', 'https://storage.mfanobora.com/docs/internet-safety.pdf', 0),
(3, 'Cybersecurity Awareness Guide', 'Awareness and prevention strategies for cybersecurity.', 'https://storage.mfanobora.com/docs/cybersecurity-awareness.pdf', 0),
(3, 'Data Protection Awareness Guide', 'Guide on data protection and privacy compliance.', 'https://storage.mfanobora.com/docs/data-protection.pdf', 0),
(3, 'Digital Marketing Guide', 'Introduction to digital marketing concepts.', 'https://storage.mfanobora.com/docs/digital-marketing.pdf', 0),
(3, 'Social Media Best Practices Guide', 'Guidelines for professional social media use.', 'https://storage.mfanobora.com/docs/social-media-practices.pdf', 0),
(3, 'ICT Career Guide', 'Information on career pathways in the ICT sector.', 'https://storage.mfanobora.com/docs/ict-career-guide.pdf', 0),
(3, 'Introduction to Programming Guide', 'Beginner concepts for computer programming.', 'https://storage.mfanobora.com/docs/intro-to-programming.pdf', 0),
(3, 'Data Literacy Guide', 'Understanding and analyzing data effectively.', 'https://storage.mfanobora.com/docs/data-literacy.pdf', 0),
(3, 'Artificial Intelligence Awareness Guide', 'Overview of AI technologies and their impact.', 'https://storage.mfanobora.com/docs/ai-awareness.pdf', 0),
(3, 'Cloud Computing Guide', 'Introduction to cloud computing infrastructure.', 'https://storage.mfanobora.com/docs/cloud-computing.pdf', 0),

-- Category 4: Mfano Africa ICT Hub
(4, 'ICT Hub Course Catalogue', 'Comprehensive list of available ICT courses.', 'https://storage.mfanobora.com/docs/ict-course-catalogue.pdf', 1),
(4, 'Computer Packages Catalogue', 'Details on standard computer package training.', 'https://storage.mfanobora.com/docs/computer-packages-catalogue.pdf', 0),
(4, 'ICT Training Programme Guide', 'Guide to the structure of ICT training programs.', 'https://storage.mfanobora.com/docs/ict-training-programme.pdf', 0),
(4, 'Digital Skills Training Guide', 'Specifics on digital skills training modules.', 'https://storage.mfanobora.com/docs/digital-skills-training.pdf', 0),
(4, 'ICT Career Pathways Guide', 'Mapping training to specific ICT career paths.', 'https://storage.mfanobora.com/docs/ict-career-pathways.pdf', 0),
(4, 'Training Registration Form', 'Form for enrolling in ICT Hub training programs.', 'https://storage.mfanobora.com/docs/training-registration-form.pdf', 0),
(4, 'ICT Training FAQ', 'Frequently asked questions regarding ICT training.', 'https://storage.mfanobora.com/docs/ict-training-faq.pdf', 0),
(4, 'Computer Packages Guide', 'Detailed syllabus for computer packages.', 'https://storage.mfanobora.com/docs/computer-packages-guide.pdf', 0),

-- Category 5: Transport & Logistics
(5, 'Transport & Logistics Guide', 'Comprehensive guide to transport and logistics operations.', 'https://storage.mfanobora.com/docs/transport-logistics-guide.pdf', 1),
(5, 'Logistics Best Practices Guide', 'Industry best practices for logistics management.', 'https://storage.mfanobora.com/docs/logistics-best-practices.pdf', 0),
(5, 'Transport Industry Overview', 'Broad overview of the East African transport sector.', 'https://storage.mfanobora.com/docs/transport-industry-overview.pdf', 0),
(5, 'Supply Chain Management Guide', 'Principles of effective supply chain management.', 'https://storage.mfanobora.com/docs/supply-chain-management.pdf', 0),
(5, 'Fleet Management Guide', 'Guide to managing commercial vehicle fleets.', 'https://storage.mfanobora.com/docs/fleet-management.pdf', 0),
(5, 'Freight & Cargo Management Guide', 'Best practices for handling freight and cargo.', 'https://storage.mfanobora.com/docs/freight-cargo-management.pdf', 0),
(5, 'Transport Operations Guide', 'Standard operating procedures for transport.', 'https://storage.mfanobora.com/docs/transport-operations.pdf', 0),
(5, 'Logistics Safety Guide', 'Safety protocols within logistics operations.', 'https://storage.mfanobora.com/docs/logistics-safety.pdf', 0),
(5, 'Transport Industry Trends Report', 'Latest trends impacting the transport industry.', 'https://storage.mfanobora.com/docs/transport-trends-report.pdf', 0),
(5, 'East Africa Transport Resources Guide', 'Resource directory for East African transport.', 'https://storage.mfanobora.com/docs/ea-transport-resources.pdf', 0),

-- Category 6: Road Safety
(6, 'Road Safety Awareness Guide', 'Educational resource promoting safer roads.', 'https://storage.mfanobora.com/docs/road-safety-awareness.pdf', 1),
(6, 'Road User Safety Guide', 'Safety guidelines for all road users.', 'https://storage.mfanobora.com/docs/road-user-safety.pdf', 0),
(6, 'Defensive Driving Guide', 'Techniques and principles for defensive driving.', 'https://storage.mfanobora.com/docs/defensive-driving.pdf', 1),
(6, 'Pedestrian Safety Guide', 'Safety protocols and awareness for pedestrians.', 'https://storage.mfanobora.com/docs/pedestrian-safety.pdf', 0),
(6, 'Motorcycle Safety Guide', 'Specific safety guidelines for motorcycle riders.', 'https://storage.mfanobora.com/docs/motorcycle-safety.pdf', 0),
(6, 'Public Transport Safety Guide', 'Safety guidelines for public transport operators and users.', 'https://storage.mfanobora.com/docs/public-transport-safety.pdf', 0),
(6, 'Road Safety Campaign Materials', 'Downloadable materials for road safety campaigns.', 'https://storage.mfanobora.com/docs/road-safety-campaigns.pdf', 0),
(6, 'Road Safety Best Practices Guide', 'Industry standard best practices for road safety.', 'https://storage.mfanobora.com/docs/road-safety-best-practices.pdf', 0),
(6, 'Road Safety Awareness Poster Pack', 'Printable posters for road safety awareness.', 'https://storage.mfanobora.com/docs/road-safety-posters.pdf', 0),
(6, 'Road Safety Statistics & Reports', 'Current data and statistics on road safety.', 'https://storage.mfanobora.com/docs/road-safety-statistics.pdf', 0),

-- Category 7: Awards & Events
(7, 'East Africa Transport, Logistics & Road Safety Awards Brochure', 'Official brochure detailing the awards programme.', 'https://storage.mfanobora.com/docs/awards-brochure.pdf', 1),
(7, 'Awards Categories Guide', 'Detailed breakdown of all award categories.', 'https://storage.mfanobora.com/docs/awards-categories.pdf', 0),
(7, 'Awards Nomination Guide', 'Instructions on how to submit a nomination.', 'https://storage.mfanobora.com/docs/awards-nomination-guide.pdf', 0),
(7, 'Awards Participation Guide', 'Information on participating in the awards event.', 'https://storage.mfanobora.com/docs/awards-participation.pdf', 0),
(7, 'Awards Eligibility Criteria', 'Criteria required to be eligible for an award.', 'https://storage.mfanobora.com/docs/awards-eligibility.pdf', 0),
(7, 'Awards Judging Criteria', 'Rubric and criteria used by the judging panel.', 'https://storage.mfanobora.com/docs/awards-judging-criteria.pdf', 0),
(7, 'Awards Sponsorship Brochure', 'Information for potential event sponsors.', 'https://storage.mfanobora.com/docs/awards-sponsorship.pdf', 0),
(7, 'Awards Event Programme', 'Schedule and programme for the awards gala.', 'https://storage.mfanobora.com/docs/awards-programme.pdf', 0),
(7, 'Previous Awards Reports', 'Summary reports of past award ceremonies.', 'https://storage.mfanobora.com/docs/previous-awards-reports.pdf', 0),
(7, 'Awards Winners & Recognition Report', 'Comprehensive list of past award winners.', 'https://storage.mfanobora.com/docs/awards-winners-report.pdf', 0),

-- Category 8: Company Resources
(8, 'Mfano Bora Africa Company Profile', 'Official corporate profile of Mfano Bora Africa.', 'https://storage.mfanobora.com/docs/company-profile.pdf', 1),
(8, 'Corporate Brochure', 'General corporate brochure outlining services.', 'https://storage.mfanobora.com/docs/corporate-brochure.pdf', 0),
(8, 'Services Brochure', 'Detailed breakdown of company services.', 'https://storage.mfanobora.com/docs/services-brochure.pdf', 0),
(8, 'Mfano Bora Africa Annual Report', 'The latest official annual report.', 'https://storage.mfanobora.com/docs/annual-report.pdf', 0),
(8, 'Company Newsletter', 'Archive of recent company newsletters.', 'https://storage.mfanobora.com/docs/company-newsletter.pdf', 0),
(8, 'Corporate Social Responsibility Report', 'Details on Mfano Bora CSR initiatives.', 'https://storage.mfanobora.com/docs/csr-report.pdf', 0),
(8, 'Company Policies & Guidelines', 'Publicly available company policies.', 'https://storage.mfanobora.com/docs/company-policies.pdf', 0),
(8, 'Partnerships & Collaboration Guide', 'Information on partnering with Mfano Bora.', 'https://storage.mfanobora.com/docs/partnerships-guide.pdf', 0),
(8, 'Mfano Bora Africa FAQ', 'Frequently asked questions about the company.', 'https://storage.mfanobora.com/docs/company-faq.pdf', 0),
(8, 'Brand Profile / Media Kit', 'Media kit and brand guidelines for press use.', 'https://storage.mfanobora.com/docs/media-kit.pdf', 0),

-- Category 9: Forms & Templates
(9, 'Attachment Application Form', 'Downloadable form for attachment application.', 'https://storage.mfanobora.com/docs/form-attachment.pdf', 0),
(9, 'Internship Application Form', 'Downloadable form for internship application.', 'https://storage.mfanobora.com/docs/form-internship.pdf', 0),
(9, 'Training Registration Form', 'Downloadable form for training registration.', 'https://storage.mfanobora.com/docs/form-training.pdf', 0),
(9, 'Resource Request Form', 'Form to request specific internal resources.', 'https://storage.mfanobora.com/docs/form-resource-request.pdf', 0),
(9, 'Partnership Enquiry Form', 'Form to initiate a partnership enquiry.', 'https://storage.mfanobora.com/docs/form-partnership.pdf', 0),
(9, 'Event Registration Form', 'Standard form for registering for events.', 'https://storage.mfanobora.com/docs/form-event-registration.pdf', 0),
(9, 'Awards Nomination Form', 'Official form to submit an award nomination.', 'https://storage.mfanobora.com/docs/form-awards-nomination.pdf', 0),
(9, 'Awards Participation Form', 'Official form to confirm awards participation.', 'https://storage.mfanobora.com/docs/form-awards-participation.pdf', 0),
(9, 'Feedback Form', 'General form for submitting feedback.', 'https://storage.mfanobora.com/docs/form-feedback.pdf', 0),
(9, 'Resource Request Template', 'Template used for bulk resource requests.', 'https://storage.mfanobora.com/docs/template-resource-request.pdf', 0),

-- Category 10: Reports, Publications & Research
(10, 'Transport Industry Reports', 'Insights and data covering the transport sector.', 'https://storage.mfanobora.com/docs/transport-industry-reports.pdf', 1),
(10, 'Logistics Industry Reports', 'In-depth analysis of logistics operations and supply chains.', 'https://storage.mfanobora.com/docs/logistics-industry-reports.pdf', 0),
(10, 'Road Safety Reports', 'Comprehensive reports on regional road safety.', 'https://storage.mfanobora.com/docs/road-safety-reports.pdf', 0),
(10, 'ICT & Digital Transformation Reports', 'Analysis of digital transformation trends.', 'https://storage.mfanobora.com/docs/ict-transformation-reports.pdf', 0),
(10, 'Youth & Employment Reports', 'Research on youth employment and career opportunities.', 'https://storage.mfanobora.com/docs/youth-employment-reports.pdf', 0),
(10, 'Skills Development Reports', 'Data on regional skills development programs.', 'https://storage.mfanobora.com/docs/skills-development-reports.pdf', 0),
(10, 'Event Reports', 'Summaries and outcomes from major industry events.', 'https://storage.mfanobora.com/docs/event-reports.pdf', 0),
(10, 'Research Publications', 'Academic and industry research publications by Mfano Bora.', 'https://storage.mfanobora.com/docs/research-publications.pdf', 0),
(10, 'Industry Insights', 'Expert commentary and insights on relevant sectors.', 'https://storage.mfanobora.com/docs/industry-insights.pdf', 0),
(10, 'Mfano Bora Africa Publications', 'Official company publications and announcements.', 'https://storage.mfanobora.com/docs/mfanobora-publications.pdf', 0);

-- Backfill the FTS index for any rows the AFTER INSERT trigger missed
-- (e.g. if resources already existed before resources_fts was created).
INSERT INTO resources_fts(rowid, title, description)
SELECT id, title, description FROM resources
WHERE id NOT IN (SELECT rowid FROM resources_fts);