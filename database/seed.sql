-- ============================================================================
-- Mfano Bora Resources Portal - Seed Data (SQLite)
-- Updated: 10 Core Categories + 102 Full Subcategories (No Sample Resources)
-- ============================================================================

PRAGMA foreign_keys = ON;

-- 1. Seed Core Categories
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

-- 2. Seed All 102 Sub-Categories
INSERT OR IGNORE INTO sub_categories (id, category_id, name, slug, description) VALUES
-- # 1. Attachment & Internship Resources
(1, 1, 'Attachment Application Form', 'attachment-application-form', 'Application form and information for industrial attachment placement.'),
(2, 1, 'Attachment Requirements', 'attachment-requirements', 'Requirements and prerequisites for attachment placement.'),
(3, 1, 'Industrial Attachment Guide', 'industrial-attachment-guide', 'Comprehensive guidelines for industrial attachment.'),
(4, 1, 'Internship Application Guide', 'internship-application-guide', 'Step-by-step guidance for internship application.'),
(5, 1, 'Student Logbook Guide', 'student-logbook-guide', 'Guide for completing student industrial logbooks.'),
(6, 1, 'Workplace Readiness Guide', 'workplace-readiness-guide', 'Resources and guides for workplace readiness.'),
(7, 1, 'Attachment Interview Preparation Guide', 'attachment-interview-preparation-guide', 'Preparation guide for industrial attachment interviews.'),
(8, 1, 'Attachment Placement Guide', 'attachment-placement-guide', 'Information and processes for attachment placement.'),
(9, 1, 'Student Attachment Checklist', 'student-attachment-checklist', 'Checklist for students commencing attachment.'),
(10, 1, 'Supervisor Assessment Guide', 'supervisor-assessment-guide', 'Evaluation and assessment guide for field supervisors.'),

-- # 2. Careers & Professional Development
(11, 2, 'CV Writing Guide', 'cv-writing-guide', 'Practical guide to writing professional resumes and CVs.'),
(12, 2, 'Cover Letter Writing Guide', 'cover-letter-writing-guide', 'Guidelines for crafting compelling cover letters.'),
(13, 2, 'Interview Preparation Guide', 'interview-preparation-guide', 'Comprehensive preparation strategies for job interviews.'),
(14, 2, 'Career Readiness Guide', 'career-readiness-guide', 'Guide to preparing for employment and professional careers.'),
(15, 2, 'Professional Ethics Guide', 'professional-ethics-guide', 'Standards and guidelines for workplace professional ethics.'),
(16, 2, 'Workplace Communication Guide', 'workplace-communication-guide', 'Best practices for effective workplace communication.'),
(17, 2, 'Time Management Guide', 'time-management-guide', 'Strategies and techniques for effective time management.'),
(18, 2, 'Teamwork & Collaboration Guide', 'teamwork-collaboration-guide', 'Principles of effective teamwork and workplace collaboration.'),
(19, 2, 'Personal Branding Guide', 'personal-branding-guide', 'Guide to establishing and managing a professional personal brand.'),
(20, 2, 'Job Application Guide', 'job-application-guide', 'Comprehensive roadmap for navigating job applications.'),
(21, 2, 'Graduate Career Guide', 'graduate-career-guide', 'Career orientation and opportunities guide for recent graduates.'),
(22, 2, 'Professional Development Guide', 'professional-development-guide', 'Strategies for continuous learning and professional growth.'),

-- # 3. ICT & Digital Skills
(23, 3, 'Basic Computer Skills Guide', 'basic-computer-skills-guide', 'Foundational computer operation and literacy guide.'),
(24, 3, 'Digital Skills Guide', 'digital-skills-guide', 'Guide to essential digital tools and productivity applications.'),
(25, 3, 'Internet Safety Guide', 'internet-safety-guide', 'Best practices for safe and secure online browsing.'),
(26, 3, 'Cybersecurity Awareness Guide', 'cybersecurity-awareness-guide', 'Awareness and prevention guidelines against cyber threats.'),
(27, 3, 'Data Protection Awareness Guide', 'data-protection-awareness-guide', 'Overview of data privacy regulations and protection practices.'),
(28, 3, 'Digital Marketing Guide', 'digital-marketing-guide', 'Introduction to digital marketing tools and online strategies.'),
(29, 3, 'Social Media Best Practices Guide', 'social-media-best-practices-guide', 'Guidelines for professional and safe social media management.'),
(30, 3, 'ICT Career Guide', 'ict-career-guide', 'Exploration of career paths within the ICT sector.'),
(31, 3, 'Introduction to Programming Guide', 'introduction-to-programming-guide', 'Beginner guidelines for software development and coding.'),
(32, 3, 'Data Literacy Guide', 'data-literacy-guide', 'Understanding, interpreting, and working with data.'),
(33, 3, 'Artificial Intelligence Awareness Guide', 'artificial-intelligence-awareness-guide', 'Overview of AI concepts, tools, and societal impacts.'),
(34, 3, 'Cloud Computing Guide', 'cloud-computing-guide', 'Introduction to cloud infrastructure and service models.'),

-- # 4. Mfano Africa ICT Hub
(35, 4, 'ICT Hub Course Catalogue', 'ict-hub-course-catalogue', 'Comprehensive directory of courses offered at the ICT Hub.'),
(36, 4, 'Computer Packages Catalogue', 'computer-packages-catalogue', 'Overview of practical short-course computer application packages.'),
(37, 4, 'ICT Training Programme Guide', 'ict-training-programme-guide', 'Structure and schedules for ICT Hub training programmes.'),
(38, 4, 'Digital Skills Training Guide', 'digital-skills-training-guide', 'Curriculum guide for digital skills hands-on training.'),
(39, 4, 'ICT Career Pathways Guide', 'ict-career-pathways-guide', 'Mapping ICT training modules to industry career roles.'),
(40, 4, 'Training Registration Form', 'ict-training-registration-form', 'Enrolment form for ICT Hub technical training courses.'),
(41, 4, 'ICT Training FAQ', 'ict-training-faq', 'Frequently asked questions regarding ICT Hub courses.'),
(42, 4, 'Computer Packages Guide', 'computer-packages-guide', 'Detailed syllabus and learning outcomes for computer packages.'),

-- # 5. Transport & Logistics
(43, 5, 'Transport & Logistics Guide', 'transport-logistics-guide', 'Overview of regional transport and logistics operations.'),
(44, 5, 'Logistics Best Practices Guide', 'logistics-best-practices-guide', 'Industry standards and efficiency benchmarks for logistics.'),
(45, 5, 'Transport Industry Overview', 'transport-industry-overview', 'Analysis and state of the East African transport sector.'),
(46, 5, 'Supply Chain Management Guide', 'supply-chain-management-guide', 'Core principles of end-to-end supply chain integration.'),
(47, 5, 'Fleet Management Guide', 'fleet-management-guide', 'Guidelines for commercial vehicle acquisition and maintenance.'),
(48, 5, 'Freight & Cargo Management Guide', 'freight-cargo-management-guide', 'Protocols for handling, routing, and securing freight.'),
(49, 5, 'Transport Operations Guide', 'transport-operations-guide', 'Standard operating procedures for commercial transport units.'),
(50, 5, 'Logistics Safety Guide', 'logistics-safety-guide', 'Safety management systems in freight and logistics operations.'),
(51, 5, 'Transport Industry Trends Report', 'transport-industry-trends-report', 'Emerging trends, technology, and economic shifts in transport.'),
(52, 5, 'East Africa Transport Resources Guide', 'east-africa-transport-resources-guide', 'Directory of regional transport policies and key hubs.'),

-- # 6. Road Safety
(53, 6, 'Road Safety Awareness Guide', 'road-safety-awareness-guide', 'Educational materials promoting road safety awareness.'),
(54, 6, 'Road User Safety Guide', 'road-user-safety-guide', 'Safety guidelines tailored for all classes of road users.'),
(55, 6, 'Defensive Driving Guide', 'defensive-driving-guide', 'Proactive risk-avoidance tactics and defensive driving rules.'),
(56, 6, 'Pedestrian Safety Guide', 'pedestrian-safety-guide', 'Safety guidelines and protocols for pedestrian infrastructure.'),
(57, 6, 'Motorcycle Safety Guide', 'motorcycle-safety-guide', 'Safety practices and gear standards for two-wheeler operators.'),
(58, 6, 'Public Transport Safety Guide', 'public-transport-safety-guide', 'Safety standards for public service vehicles and commuters.'),
(59, 6, 'Road Safety Campaign Materials', 'road-safety-campaign-materials', 'Resources and artwork for conducting safety campaigns.'),
(60, 6, 'Road Safety Best Practices Guide', 'road-safety-best-practices-guide', 'Proven operational models for improving road safety outcomes.'),
(61, 6, 'Road Safety Awareness Poster Pack', 'road-safety-awareness-poster-pack', 'Printable visual graphics for community safety awareness.'),
(62, 6, 'Road Safety Statistics & Reports', 'road-safety-statistics-reports', 'Statistical analysis and annual reports on traffic accident data.'),

-- # 7. Awards & Events
(63, 7, 'East Africa Transport, Logistics & Road Safety Awards Brochure', 'east-africa-transport-logistics-road-safety-awards-brochure', 'Official informational brochure for the regional awards gala.'),
(64, 7, 'Awards Categories Guide', 'awards-categories-guide', 'Detailed description of nomination categories and requirements.'),
(65, 7, 'Awards Nomination Guide', 'awards-nomination-guide', 'Guidelines and step-by-step instructions for submitting entry nominations.'),
(66, 7, 'Awards Participation Guide', 'awards-participation-guide', 'Delegate and participant guide for attending the event.'),
(67, 7, 'Awards Eligibility Criteria', 'awards-eligibility-criteria', 'Prerequisites and compliance criteria for award entrants.'),
(68, 7, 'Awards Judging Criteria', 'awards-judging-criteria', 'Evaluation framework and scoring metrics used by judges.'),
(69, 7, 'Awards Sponsorship Brochure', 'awards-sponsorship-brochure', 'Sponsorship packages and brand visibility opportunities.'),
(70, 7, 'Awards Event Programme', 'awards-event-programme', 'Official agenda, session schedules, and gala lineup.'),
(71, 7, 'Previous Awards Reports', 'previous-awards-reports', 'Retrospective reports and summaries from past award editions.'),
(72, 7, 'Awards Winners & Recognition Report', 'awards-winners-recognition-report', 'Official announcement and profile of past award recipients.'),

-- # 8. Company Resources
(73, 8, 'Mfano Bora Africa Company Profile', 'mfano-bora-africa-company-profile', 'Official overview of Mfano Bora Africa mandate and vision.'),
(74, 8, 'Corporate Brochure', 'corporate-brochure', 'General corporate summary of organizational capability.'),
(75, 8, 'Services Brochure', 'services-brochure', 'Detailed catalog of professional services and consultancies.'),
(76, 8, 'Mfano Bora Africa Annual Report', 'mfano-bora-africa-annual-report', 'Annual financial and operational performance summary report.'),
(77, 8, 'Company Newsletter', 'company-newsletter', 'Quarterly updates and news highlights from the organization.'),
(78, 8, 'Corporate Social Responsibility Report', 'corporate-social-responsibility-report', 'Summary of social impact, community, and sustainability initiatives.'),
(79, 8, 'Company Policies & Guidelines', 'company-policies-guidelines', 'Operational policies, code of conduct, and public guidelines.'),
(80, 8, 'Partnerships & Collaboration Guide', 'partnerships-collaboration-guide', 'Framework for strategic business partnerships and stakeholder engagement.'),
(81, 8, 'Mfano Bora Africa FAQ', 'mfano-bora-africa-faq', 'Answers to frequently asked corporate inquiries.'),
(82, 8, 'Brand Profile / Media Kit', 'brand-profile-media-kit', 'Media assets, logo guidelines, and official press kits.'),

-- # 9. Forms & Templates
(83, 9, 'Attachment Application Form', 'forms-attachment-application-form', 'Standard printable form for industrial attachment applications.'),
(84, 9, 'Internship Application Form', 'forms-internship-application-form', 'Standard application form for open internship positions.'),
(85, 9, 'Training Registration Form', 'forms-training-registration-form', 'General enrolment form for professional training programs.'),
(86, 9, 'Resource Request Form', 'forms-resource-request-form', 'Form to request custom data, reports, or materials.'),
(87, 9, 'Partnership Enquiry Form', 'forms-partnership-enquiry-form', 'Official form to submit strategic partnership proposals.'),
(88, 9, 'Event Registration Form', 'forms-event-registration-form', 'Participant registration form for upcoming events and workshops.'),
(89, 9, 'Awards Nomination Form', 'forms-awards-nomination-form', 'Official nomination submission template for annual awards.'),
(90, 9, 'Awards Participation Form', 'forms-awards-participation-form', 'Confirmation form for award ceremony attendees and finalists.'),
(91, 9, 'Feedback Form', 'forms-feedback-form', 'General user feedback and inquiry form.'),
(92, 9, 'Resource Request Template', 'forms-resource-request-template', 'Document template for bulk or enterprise resource requests.'),

-- # 10. Reports, Publications & Research
(93, 10, 'Transport Industry Reports', 'transport-industry-reports', 'In-depth analytical reports on regional transport infrastructure.'),
(94, 10, 'Logistics Industry Reports', 'logistics-industry-reports', 'Market research and trends in supply chain and logistics.'),
(95, 10, 'Road Safety Reports', 'road-safety-reports', 'Research papers and evaluation reports on road safety interventions.'),
(96, 10, 'ICT & Digital Transformation Reports', 'ict-digital-transformation-reports', 'Studies on digital technology adoption and IT infrastructure.'),
(97, 10, 'Youth & Employment Reports', 'youth-employment-reports', 'Socio-economic research on youth employment and labor dynamics.'),
(98, 10, 'Skills Development Reports', 'skills-development-reports', 'Assessments of technical skills gaps and training impact.'),
(99, 10, 'Event Reports', 'event-reports', 'Post-event proceedings, summaries, and communique papers.'),
(100, 10, 'Research Publications', 'research-publications', 'Peer-reviewed and institutional research publications.'),
(101, 10, 'Industry Insights', 'industry-insights', 'Briefs and expert commentary on key industry developments.'),
(102, 10, 'Mfano Bora Africa Publications', 'mfano-bora-africa-publications', 'Official organizational whitepapers and institutional reports.');