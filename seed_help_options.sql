-- ============================================================================
-- PEARS GLOBAL / UNITY APP - SEED GET HELP OPTIONS (STANDARD SQL)
-- Compatible with ALL web clients (phpPgAdmin, Adminer, pgAdmin, cPanel, CLI)
-- Generated on: 2026-10-12 14:19:49
-- ============================================================================

-- 1. Ensure 'help' Flow exists
INSERT INTO ask_flows (id, code, name, description, sort_order, is_active, created_at, updated_at)
VALUES (gen_random_uuid(), 'help', 'Get Help', 'Ask peers for advice, mentorship, tasks, introductions and support.', 3, true, NOW(), NOW())
ON CONFLICT (code) DO UPDATE SET is_active = true, updated_at = NOW();

-- 2. Ensure Option Groups exist
INSERT INTO ask_option_groups (id, code, name, description, input_type, is_multi_select, is_required, is_active, sort_order, metadata, created_at, updated_at)
VALUES (gen_random_uuid(), 'help_subtype', 'Subtype', 'Select one or more help subtypes.', 'multi_select', true, false, true, 8, '{"flows": ["help"]}'::jsonb, NOW(), NOW())
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    input_type = EXCLUDED.input_type,
    is_multi_select = EXCLUDED.is_multi_select,
    metadata = EXCLUDED.metadata,
    is_active = true,
    updated_at = NOW();

INSERT INTO ask_option_groups (id, code, name, description, input_type, is_multi_select, is_required, is_active, sort_order, metadata, created_at, updated_at)
VALUES (gen_random_uuid(), 'help_topic', 'Topic', 'Select one or more help topics.', 'multi_select', true, false, true, 9, '{"flows": ["help"]}'::jsonb, NOW(), NOW())
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    input_type = EXCLUDED.input_type,
    is_multi_select = EXCLUDED.is_multi_select,
    metadata = EXCLUDED.metadata,
    is_active = true,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Advice (advice)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'advice', 'Advice', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'advice'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('adv_subtype_business_advice', 'Business Advice', 1),
    ('adv_subtype_startup_advice', 'Startup Advice', 2),
    ('adv_subtype_marketing_advice', 'Marketing Advice', 3),
    ('adv_subtype_sales_advice', 'Sales Advice', 4),
    ('adv_subtype_finance_advice', 'Finance Advice', 5),
    ('adv_subtype_legal_compliance_advice', 'Legal & Compliance Advice', 6),
    ('adv_subtype_technology_advice', 'Technology Advice', 7),
    ('adv_subtype_operations_advice', 'Operations Advice', 8),
    ('adv_subtype_human_resources_advice', 'Human Resources Advice', 9),
    ('adv_subtype_leadership_advice', 'Leadership Advice', 10),
    ('adv_subtype_strategy_growth_advice', 'Strategy & Growth Advice', 11),
    ('adv_subtype_product_development_advice', 'Product Development Advice', 12),
    ('adv_subtype_customer_acquisition_advice', 'Customer Acquisition Advice', 13),
    ('adv_subtype_international_business_advice', 'International Business Advice', 14),
    ('adv_subtype_personal_professional_development', 'Personal & Professional Development', 15),
    ('adv_subtype_other', 'Other', 16)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'advice'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('adv_topic_business_planning', 'Business Planning', 1),
    ('adv_topic_business_model', 'Business Model', 2),
    ('adv_topic_business_registration', 'Business Registration', 3),
    ('adv_topic_idea_validation', 'Idea Validation', 4),
    ('adv_topic_business_expansion', 'Business Expansion', 5),
    ('adv_topic_business_challenges', 'Business Challenges', 6),
    ('adv_topic_decision_making', 'Decision-Making', 7),
    ('adv_topic_digital_marketing', 'Digital Marketing', 8),
    ('adv_topic_branding', 'Branding', 9),
    ('adv_topic_social_media', 'Social Media', 10),
    ('adv_topic_content_marketing', 'Content Marketing', 11),
    ('adv_topic_advertising', 'Advertising', 12),
    ('adv_topic_seo', 'SEO', 13),
    ('adv_topic_lead_generation', 'Lead Generation', 14),
    ('adv_topic_sales_strategy', 'Sales Strategy', 15),
    ('adv_topic_negotiation', 'Negotiation', 16),
    ('adv_topic_customer_conversion', 'Customer Conversion', 17),
    ('adv_topic_pricing', 'Pricing', 18),
    ('adv_topic_cash_flow', 'Cash Flow', 19),
    ('adv_topic_budgeting', 'Budgeting', 20),
    ('adv_topic_financial_planning', 'Financial Planning', 21),
    ('adv_topic_tax_planning', 'Tax Planning', 22),
    ('adv_topic_fundraising', 'Fundraising', 23),
    ('adv_topic_company_registration', 'Company Registration', 24),
    ('adv_topic_contracts', 'Contracts', 25),
    ('adv_topic_intellectual_property', 'Intellectual Property', 26),
    ('adv_topic_gst_compliance', 'GST & Compliance', 27),
    ('adv_topic_ai_automation', 'AI & Automation', 28),
    ('adv_topic_digital_transformation', 'Digital Transformation', 29),
    ('adv_topic_cybersecurity', 'Cybersecurity', 30),
    ('adv_topic_supply_chain', 'Supply Chain', 31),
    ('adv_topic_inventory_management', 'Inventory Management', 32),
    ('adv_topic_team_building', 'Team Building', 33),
    ('adv_topic_employee_retention', 'Employee Retention', 34),
    ('adv_topic_leadership_skills', 'Leadership Skills', 35),
    ('adv_topic_conflict_resolution', 'Conflict Resolution', 36),
    ('adv_topic_market_expansion', 'Market Expansion', 37),
    ('adv_topic_scaling_a_business', 'Scaling a Business', 38),
    ('adv_topic_revenue_growth', 'Revenue Growth', 39),
    ('adv_topic_product_market_fit', 'Product-Market Fit', 40),
    ('adv_topic_customer_retention', 'Customer Retention', 41),
    ('adv_topic_international_trade', 'International Trade', 42),
    ('adv_topic_other', 'Other', 43)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Mentorship (mentorship)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'mentorship', 'Mentorship', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'mentorship'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('ment_subtype_founder_mentorship', 'Founder Mentorship', 1),
    ('ment_subtype_first_time_founder_mentorship', 'First-Time Founder Mentorship', 2),
    ('ment_subtype_industry_mentorship', 'Industry Mentorship', 3),
    ('ment_subtype_expert_guidance', 'Expert Guidance', 4),
    ('ment_subtype_business_growth_mentorship', 'Business Growth Mentorship', 5),
    ('ment_subtype_leadership_mentorship', 'Leadership Mentorship', 6),
    ('ment_subtype_startup_mentorship', 'Startup Mentorship', 7),
    ('ment_subtype_career_mentorship', 'Career Mentorship', 8),
    ('ment_subtype_peer_mentorship', 'Peer Mentorship', 9),
    ('ment_subtype_accountability', 'Accountability', 10),
    ('ment_subtype_technical_mentorship', 'Technical Mentorship', 11),
    ('ment_subtype_other', 'Other', 12)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'mentorship'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('ment_topic_sales_growth', 'Sales & Growth', 1),
    ('ment_topic_finance', 'Finance', 2),
    ('ment_topic_leadership', 'Leadership', 3),
    ('ment_topic_operations', 'Operations', 4),
    ('ment_topic_marketing', 'Marketing', 5),
    ('ment_topic_business_strategy', 'Business Strategy', 6),
    ('ment_topic_fundraising', 'Fundraising', 7),
    ('ment_topic_investor_readiness', 'Investor Readiness', 8),
    ('ment_topic_startup_roadmap', 'Startup Roadmap', 9),
    ('ment_topic_business_planning', 'Business Planning', 10),
    ('ment_topic_idea_validation', 'Idea Validation', 11),
    ('ment_topic_product_development', 'Product Development', 12),
    ('ment_topic_product_market_fit', 'Product-Market Fit', 13),
    ('ment_topic_customer_acquisition', 'Customer Acquisition', 14),
    ('ment_topic_team_management', 'Team Management', 15),
    ('ment_topic_decision_making', 'Decision-Making', 16),
    ('ment_topic_business_scaling', 'Business Scaling', 17),
    ('ment_topic_strategic_partnerships', 'Strategic Partnerships', 18),
    ('ment_topic_technology', 'Technology', 19),
    ('ment_topic_ai_automation', 'AI & Automation', 20),
    ('ment_topic_career_development', 'Career Development', 21),
    ('ment_topic_professional_networking', 'Professional Networking', 22),
    ('ment_topic_personal_development', 'Personal Development', 23),
    ('ment_topic_goal_setting', 'Goal Setting', 24),
    ('ment_topic_execution_accountability', 'Execution & Accountability', 25),
    ('ment_topic_industry_knowledge', 'Industry Knowledge', 26),
    ('ment_topic_work_life_balance', 'Work-Life Balance', 27),
    ('ment_topic_other', 'Other', 28)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Founder Mentorship (founder_mentorship)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'founder_mentorship', 'Founder Mentorship', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'founder_mentorship'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('fment_subtype_founder_mentorship', 'Founder Mentorship', 1),
    ('fment_subtype_first_time_founder_mentorship', 'First-Time Founder Mentorship', 2),
    ('fment_subtype_industry_mentorship', 'Industry Mentorship', 3),
    ('fment_subtype_expert_guidance', 'Expert Guidance', 4),
    ('fment_subtype_business_growth_mentorship', 'Business Growth Mentorship', 5),
    ('fment_subtype_leadership_mentorship', 'Leadership Mentorship', 6),
    ('fment_subtype_startup_mentorship', 'Startup Mentorship', 7),
    ('fment_subtype_career_mentorship', 'Career Mentorship', 8),
    ('fment_subtype_peer_mentorship', 'Peer Mentorship', 9),
    ('fment_subtype_accountability', 'Accountability', 10),
    ('fment_subtype_technical_mentorship', 'Technical Mentorship', 11),
    ('fment_subtype_other', 'Other', 12)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'founder_mentorship'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('fment_topic_sales_growth', 'Sales & Growth', 1),
    ('fment_topic_finance', 'Finance', 2),
    ('fment_topic_leadership', 'Leadership', 3),
    ('fment_topic_operations', 'Operations', 4),
    ('fment_topic_marketing', 'Marketing', 5),
    ('fment_topic_business_strategy', 'Business Strategy', 6),
    ('fment_topic_fundraising', 'Fundraising', 7),
    ('fment_topic_investor_readiness', 'Investor Readiness', 8),
    ('fment_topic_startup_roadmap', 'Startup Roadmap', 9),
    ('fment_topic_business_planning', 'Business Planning', 10),
    ('fment_topic_idea_validation', 'Idea Validation', 11),
    ('fment_topic_product_development', 'Product Development', 12),
    ('fment_topic_product_market_fit', 'Product-Market Fit', 13),
    ('fment_topic_customer_acquisition', 'Customer Acquisition', 14),
    ('fment_topic_team_management', 'Team Management', 15),
    ('fment_topic_decision_making', 'Decision-Making', 16),
    ('fment_topic_business_scaling', 'Business Scaling', 17),
    ('fment_topic_strategic_partnerships', 'Strategic Partnerships', 18),
    ('fment_topic_technology', 'Technology', 19),
    ('fment_topic_ai_automation', 'AI & Automation', 20),
    ('fment_topic_career_development', 'Career Development', 21),
    ('fment_topic_professional_networking', 'Professional Networking', 22),
    ('fment_topic_personal_development', 'Personal Development', 23),
    ('fment_topic_goal_setting', 'Goal Setting', 24),
    ('fment_topic_execution_accountability', 'Execution & Accountability', 25),
    ('fment_topic_industry_knowledge', 'Industry Knowledge', 26),
    ('fment_topic_work_life_balance', 'Work-Life Balance', 27),
    ('fment_topic_other', 'Other', 28)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Industry Mentorship (industry_mentorship)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'industry_mentorship', 'Industry Mentorship', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'industry_mentorship'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('iment_subtype_founder_mentorship', 'Founder Mentorship', 1),
    ('iment_subtype_first_time_founder_mentorship', 'First-Time Founder Mentorship', 2),
    ('iment_subtype_industry_mentorship', 'Industry Mentorship', 3),
    ('iment_subtype_expert_guidance', 'Expert Guidance', 4),
    ('iment_subtype_business_growth_mentorship', 'Business Growth Mentorship', 5),
    ('iment_subtype_leadership_mentorship', 'Leadership Mentorship', 6),
    ('iment_subtype_startup_mentorship', 'Startup Mentorship', 7),
    ('iment_subtype_career_mentorship', 'Career Mentorship', 8),
    ('iment_subtype_peer_mentorship', 'Peer Mentorship', 9),
    ('iment_subtype_accountability', 'Accountability', 10),
    ('iment_subtype_technical_mentorship', 'Technical Mentorship', 11),
    ('iment_subtype_other', 'Other', 12)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'industry_mentorship'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('iment_topic_sales_growth', 'Sales & Growth', 1),
    ('iment_topic_finance', 'Finance', 2),
    ('iment_topic_leadership', 'Leadership', 3),
    ('iment_topic_operations', 'Operations', 4),
    ('iment_topic_marketing', 'Marketing', 5),
    ('iment_topic_business_strategy', 'Business Strategy', 6),
    ('iment_topic_fundraising', 'Fundraising', 7),
    ('iment_topic_investor_readiness', 'Investor Readiness', 8),
    ('iment_topic_startup_roadmap', 'Startup Roadmap', 9),
    ('iment_topic_business_planning', 'Business Planning', 10),
    ('iment_topic_idea_validation', 'Idea Validation', 11),
    ('iment_topic_product_development', 'Product Development', 12),
    ('iment_topic_product_market_fit', 'Product-Market Fit', 13),
    ('iment_topic_customer_acquisition', 'Customer Acquisition', 14),
    ('iment_topic_team_management', 'Team Management', 15),
    ('iment_topic_decision_making', 'Decision-Making', 16),
    ('iment_topic_business_scaling', 'Business Scaling', 17),
    ('iment_topic_strategic_partnerships', 'Strategic Partnerships', 18),
    ('iment_topic_technology', 'Technology', 19),
    ('iment_topic_ai_automation', 'AI & Automation', 20),
    ('iment_topic_career_development', 'Career Development', 21),
    ('iment_topic_professional_networking', 'Professional Networking', 22),
    ('iment_topic_personal_development', 'Personal Development', 23),
    ('iment_topic_goal_setting', 'Goal Setting', 24),
    ('iment_topic_execution_accountability', 'Execution & Accountability', 25),
    ('iment_topic_industry_knowledge', 'Industry Knowledge', 26),
    ('iment_topic_work_life_balance', 'Work-Life Balance', 27),
    ('iment_topic_other', 'Other', 28)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Expert Guidance (expert_guidance)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'expert_guidance', 'Expert Guidance', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'expert_guidance'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('eg_subtype_founder_mentorship', 'Founder Mentorship', 1),
    ('eg_subtype_first_time_founder_mentorship', 'First-Time Founder Mentorship', 2),
    ('eg_subtype_industry_mentorship', 'Industry Mentorship', 3),
    ('eg_subtype_expert_guidance', 'Expert Guidance', 4),
    ('eg_subtype_business_growth_mentorship', 'Business Growth Mentorship', 5),
    ('eg_subtype_leadership_mentorship', 'Leadership Mentorship', 6),
    ('eg_subtype_startup_mentorship', 'Startup Mentorship', 7),
    ('eg_subtype_career_mentorship', 'Career Mentorship', 8),
    ('eg_subtype_peer_mentorship', 'Peer Mentorship', 9),
    ('eg_subtype_accountability', 'Accountability', 10),
    ('eg_subtype_technical_mentorship', 'Technical Mentorship', 11),
    ('eg_subtype_other', 'Other', 12)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'expert_guidance'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('eg_topic_sales_growth', 'Sales & Growth', 1),
    ('eg_topic_finance', 'Finance', 2),
    ('eg_topic_leadership', 'Leadership', 3),
    ('eg_topic_operations', 'Operations', 4),
    ('eg_topic_marketing', 'Marketing', 5),
    ('eg_topic_business_strategy', 'Business Strategy', 6),
    ('eg_topic_fundraising', 'Fundraising', 7),
    ('eg_topic_investor_readiness', 'Investor Readiness', 8),
    ('eg_topic_startup_roadmap', 'Startup Roadmap', 9),
    ('eg_topic_business_planning', 'Business Planning', 10),
    ('eg_topic_idea_validation', 'Idea Validation', 11),
    ('eg_topic_product_development', 'Product Development', 12),
    ('eg_topic_product_market_fit', 'Product-Market Fit', 13),
    ('eg_topic_customer_acquisition', 'Customer Acquisition', 14),
    ('eg_topic_team_management', 'Team Management', 15),
    ('eg_topic_decision_making', 'Decision-Making', 16),
    ('eg_topic_business_scaling', 'Business Scaling', 17),
    ('eg_topic_strategic_partnerships', 'Strategic Partnerships', 18),
    ('eg_topic_technology', 'Technology', 19),
    ('eg_topic_ai_automation', 'AI & Automation', 20),
    ('eg_topic_career_development', 'Career Development', 21),
    ('eg_topic_professional_networking', 'Professional Networking', 22),
    ('eg_topic_personal_development', 'Personal Development', 23),
    ('eg_topic_goal_setting', 'Goal Setting', 24),
    ('eg_topic_execution_accountability', 'Execution & Accountability', 25),
    ('eg_topic_industry_knowledge', 'Industry Knowledge', 26),
    ('eg_topic_work_life_balance', 'Work-Life Balance', 27),
    ('eg_topic_other', 'Other', 28)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Small Task (small_task)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'small_task', 'Small Task', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'small_task'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('task_subtype_technical_help', 'Technical Help', 1),
    ('task_subtype_website_app_help', 'Website / App Help', 2),
    ('task_subtype_design_help', 'Design Help', 3),
    ('task_subtype_content_writing', 'Content Writing', 4),
    ('task_subtype_research_help', 'Research Help', 5),
    ('task_subtype_marketing_task', 'Marketing Task', 6),
    ('task_subtype_sales_support', 'Sales Support', 7),
    ('task_subtype_business_documentation', 'Business Documentation', 8),
    ('task_subtype_data_entry_data_management', 'Data Entry / Data Management', 9),
    ('task_subtype_administrative_support', 'Administrative Support', 10),
    ('task_subtype_finance_accounting_task', 'Finance / Accounting Task', 11),
    ('task_subtype_legal_documentation_support', 'Legal Documentation Support', 12),
    ('task_subtype_presentation_pitch_deck', 'Presentation / Pitch Deck', 13),
    ('task_subtype_social_media_assistance', 'Social Media Assistance', 14),
    ('task_subtype_translation_language_support', 'Translation / Language Support', 15),
    ('task_subtype_other', 'Other', 16)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'small_task'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('task_topic_bug_fixing', 'Bug Fixing', 1),
    ('task_topic_coding', 'Coding', 2),
    ('task_topic_api_integration', 'API Integration', 3),
    ('task_topic_software_setup', 'Software Setup', 4),
    ('task_topic_automation', 'Automation', 5),
    ('task_topic_website_updates', 'Website Updates', 6),
    ('task_topic_wordpress', 'WordPress', 7),
    ('task_topic_landing_page', 'Landing Page', 8),
    ('task_topic_mobile_app_testing', 'Mobile App Testing', 9),
    ('task_topic_uiux', 'UI/UX', 10),
    ('task_topic_logo_design', 'Logo Design', 11),
    ('task_topic_graphic_design', 'Graphic Design', 12),
    ('task_topic_social_media_creatives', 'Social Media Creatives', 13),
    ('task_topic_brochure_design', 'Brochure Design', 14),
    ('task_topic_blog_writing', 'Blog Writing', 15),
    ('task_topic_copywriting', 'Copywriting', 16),
    ('task_topic_email_writing', 'Email Writing', 17),
    ('task_topic_proofreading', 'Proofreading', 18),
    ('task_topic_market_research', 'Market Research', 19),
    ('task_topic_competitor_analysis', 'Competitor Analysis', 20),
    ('task_topic_data_collection', 'Data Collection', 21),
    ('task_topic_seo', 'SEO', 22),
    ('task_topic_campaign_setup', 'Campaign Setup', 23),
    ('task_topic_lead_generation', 'Lead Generation', 24),
    ('task_topic_crm_updates', 'CRM Updates', 25),
    ('task_topic_proposal_preparation', 'Proposal Preparation', 26),
    ('task_topic_business_plan', 'Business Plan', 27),
    ('task_topic_sop_documentation', 'SOP Documentation', 28),
    ('task_topic_spreadsheet_management', 'Spreadsheet Management', 29),
    ('task_topic_data_cleaning', 'Data Cleaning', 30),
    ('task_topic_invoice_preparation', 'Invoice Preparation', 31),
    ('task_topic_bookkeeping', 'Bookkeeping', 32),
    ('task_topic_financial_spreadsheet', 'Financial Spreadsheet', 33),
    ('task_topic_presentation_design', 'Presentation Design', 34),
    ('task_topic_pitch_deck', 'Pitch Deck', 35),
    ('task_topic_content_calendar', 'Content Calendar', 36),
    ('task_topic_translation', 'Translation', 37),
    ('task_topic_other', 'Other', 38)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Introduction (introduction)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'introduction', 'Introduction', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'introduction'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('intro_subtype_investor_introduction', 'Investor Introduction', 1),
    ('intro_subtype_customer_introduction', 'Customer Introduction', 2),
    ('intro_subtype_business_partner_introduction', 'Business Partner Introduction', 3),
    ('intro_subtype_co_founder_introduction', 'Co-Founder Introduction', 4),
    ('intro_subtype_mentor_introduction', 'Mentor Introduction', 5),
    ('intro_subtype_industry_expert_introduction', 'Industry Expert Introduction', 6),
    ('intro_subtype_supplier_vendor_introduction', 'Supplier / Vendor Introduction', 7),
    ('intro_subtype_distributor_introduction', 'Distributor Introduction', 8),
    ('intro_subtype_corporate_introduction', 'Corporate Introduction', 9),
    ('intro_subtype_government_institutional_introduction', 'Government / Institutional Introduction', 10),
    ('intro_subtype_media_pr_introduction', 'Media / PR Introduction', 11),
    ('intro_subtype_strategic_network_introduction', 'Strategic Network Introduction', 12),
    ('intro_subtype_international_business_introduction', 'International Business Introduction', 13),
    ('intro_subtype_other', 'Other', 14)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'introduction'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('intro_topic_angel_investors', 'Angel Investors', 1),
    ('intro_topic_venture_capitalists', 'Venture Capitalists', 2),
    ('intro_topic_family_offices', 'Family Offices', 3),
    ('intro_topic_strategic_investors', 'Strategic Investors', 4),
    ('intro_topic_potential_customers', 'Potential Customers', 5),
    ('intro_topic_enterprise_clients', 'Enterprise Clients', 6),
    ('intro_topic_corporate_buyers', 'Corporate Buyers', 7),
    ('intro_topic_joint_venture_partners', 'Joint Venture Partners', 8),
    ('intro_topic_strategic_partners', 'Strategic Partners', 9),
    ('intro_topic_technology_partners', 'Technology Partners', 10),
    ('intro_topic_manufacturing_partners', 'Manufacturing Partners', 11),
    ('intro_topic_technical_co_founder', 'Technical Co-Founder', 12),
    ('intro_topic_business_co_founder', 'Business Co-Founder', 13),
    ('intro_topic_sales_co_founder', 'Sales Co-Founder', 14),
    ('intro_topic_founder_mentors', 'Founder Mentors', 15),
    ('intro_topic_industry_leaders', 'Industry Leaders', 16),
    ('intro_topic_subject_matter_experts', 'Subject Matter Experts', 17),
    ('intro_topic_raw_material_suppliers', 'Raw Material Suppliers', 18),
    ('intro_topic_manufacturers', 'Manufacturers', 19),
    ('intro_topic_service_providers', 'Service Providers', 20),
    ('intro_topic_regional_distributors', 'Regional Distributors', 21),
    ('intro_topic_national_distributors', 'National Distributors', 22),
    ('intro_topic_international_distributors', 'International Distributors', 23),
    ('intro_topic_corporate_decision_makers', 'Corporate Decision-Makers', 24),
    ('intro_topic_procurement_heads', 'Procurement Heads', 25),
    ('intro_topic_incubators', 'Incubators', 26),
    ('intro_topic_accelerators', 'Accelerators', 27),
    ('intro_topic_industry_associations', 'Industry Associations', 28),
    ('intro_topic_government_departments', 'Government Departments', 29),
    ('intro_topic_journalists', 'Journalists', 30),
    ('intro_topic_media_houses', 'Media Houses', 31),
    ('intro_topic_podcast_hosts', 'Podcast Hosts', 32),
    ('intro_topic_influencers', 'Influencers', 33),
    ('intro_topic_international_business_contacts', 'International Business Contacts', 34),
    ('intro_topic_exporters_importers', 'Exporters / Importers', 35),
    ('intro_topic_other', 'Other', 36)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Capital Support (capital_support)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'capital_support', 'Capital Support', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'capital_support'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('cap_subtype_funding_guidance', 'Funding Guidance', 1),
    ('cap_subtype_investor_introduction', 'Investor Introduction', 2),
    ('cap_subtype_equity_funding', 'Equity Funding', 3),
    ('cap_subtype_debt_funding', 'Debt Funding', 4),
    ('cap_subtype_business_loan_guidance', 'Business Loan Guidance', 5),
    ('cap_subtype_government_scheme_guidance', 'Government Scheme Guidance', 6),
    ('cap_subtype_grant_subsidy_guidance', 'Grant / Subsidy Guidance', 7),
    ('cap_subtype_working_capital_support', 'Working Capital Support', 8),
    ('cap_subtype_financial_planning', 'Financial Planning', 9),
    ('cap_subtype_fundraising_strategy', 'Fundraising Strategy', 10),
    ('cap_subtype_investor_pitch_preparation', 'Investor Pitch Preparation', 11),
    ('cap_subtype_debt_restructuring_guidance', 'Debt Restructuring Guidance', 12),
    ('cap_subtype_financial_institution_introduction', 'Financial Institution Introduction', 13),
    ('cap_subtype_other', 'Other', 14)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'capital_support'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('cap_topic_seed_funding', 'Seed Funding', 1),
    ('cap_topic_pre_seed_funding', 'Pre-Seed Funding', 2),
    ('cap_topic_angel_investment', 'Angel Investment', 3),
    ('cap_topic_venture_capital', 'Venture Capital', 4),
    ('cap_topic_growth_equity', 'Growth Equity', 5),
    ('cap_topic_strategic_investment', 'Strategic Investment', 6),
    ('cap_topic_business_loans', 'Business Loans', 7),
    ('cap_topic_working_capital_loans', 'Working Capital Loans', 8),
    ('cap_topic_equipment_finance', 'Equipment Finance', 9),
    ('cap_topic_secured_loans', 'Secured Loans', 10),
    ('cap_topic_loan_eligibility', 'Loan Eligibility', 11),
    ('cap_topic_loan_documentation', 'Loan Documentation', 12),
    ('cap_topic_credit_assessment', 'Credit Assessment', 13),
    ('cap_topic_msme_schemes', 'MSME Schemes', 14),
    ('cap_topic_startup_india_schemes', 'Startup India Schemes', 15),
    ('cap_topic_state_government_schemes', 'State Government Schemes', 16),
    ('cap_topic_central_government_schemes', 'Central Government Schemes', 17),
    ('cap_topic_startup_grants', 'Startup Grants', 18),
    ('cap_topic_innovation_grants', 'Innovation Grants', 19),
    ('cap_topic_research_grants', 'Research Grants', 20),
    ('cap_topic_industry_subsidies', 'Industry Subsidies', 21),
    ('cap_topic_cash_flow_planning', 'Cash Flow Planning', 22),
    ('cap_topic_financial_forecasting', 'Financial Forecasting', 23),
    ('cap_topic_budgeting', 'Budgeting', 24),
    ('cap_topic_financial_modelling', 'Financial Modelling', 25),
    ('cap_topic_investor_targeting', 'Investor Targeting', 26),
    ('cap_topic_business_valuation', 'Business Valuation', 27),
    ('cap_topic_fundraising_roadmap', 'Fundraising Roadmap', 28),
    ('cap_topic_pitch_deck', 'Pitch Deck', 29),
    ('cap_topic_financial_projections', 'Financial Projections', 30),
    ('cap_topic_due_diligence', 'Due Diligence', 31),
    ('cap_topic_debt_repayment_planning', 'Debt Repayment Planning', 32),
    ('cap_topic_debt_consolidation', 'Debt Consolidation', 33),
    ('cap_topic_bank_introduction', 'Bank Introduction', 34),
    ('cap_topic_nbfc_introduction', 'NBFC Introduction', 35),
    ('cap_topic_investment_firm_introduction', 'Investment Firm Introduction', 36),
    ('cap_topic_other', 'Other', 37)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Emotional / Peer Support (emotional_peer_support)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'emotional_peer_support', 'Emotional / Peer Support', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'emotional_peer_support'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('peer_subtype_founder_peer_support', 'Founder Peer Support', 1),
    ('peer_subtype_stress_burnout_support', 'Stress / Burnout Support', 2),
    ('peer_subtype_motivation_encouragement', 'Motivation & Encouragement', 3),
    ('peer_subtype_work_life_balance', 'Work-Life Balance', 4),
    ('peer_subtype_founder_isolation', 'Founder Isolation', 5),
    ('peer_subtype_confidence_building', 'Confidence Building', 6),
    ('peer_subtype_personal_challenges', 'Personal Challenges', 7),
    ('peer_subtype_experience_sharing', 'Experience Sharing', 8),
    ('peer_subtype_community_connection', 'Community Connection', 9),
    ('peer_subtype_other', 'Other', 10)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'emotional_peer_support'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('peer_topic_startup_challenges', 'Startup Challenges', 1),
    ('peer_topic_founder_experiences', 'Founder Experiences', 2),
    ('peer_topic_business_pressure', 'Business Pressure', 3),
    ('peer_topic_difficult_decisions', 'Difficult Decisions', 4),
    ('peer_topic_stress_management', 'Stress Management', 5),
    ('peer_topic_burnout_prevention', 'Burnout Prevention', 6),
    ('peer_topic_managing_workload', 'Managing Workload', 7),
    ('peer_topic_motivation', 'Motivation', 8),
    ('peer_topic_overcoming_setbacks', 'Overcoming Setbacks', 9),
    ('peer_topic_staying_consistent', 'Staying Consistent', 10),
    ('peer_topic_time_management', 'Time Management', 11),
    ('peer_topic_family_business', 'Family & Business', 12),
    ('peer_topic_personal_time', 'Personal Time', 13),
    ('peer_topic_finding_like_minded_founders', 'Finding Like-Minded Founders', 14),
    ('peer_topic_peer_connection', 'Peer Connection', 15),
    ('peer_topic_community_support', 'Community Support', 16),
    ('peer_topic_self_confidence', 'Self-Confidence', 17),
    ('peer_topic_public_speaking_confidence', 'Public Speaking Confidence', 18),
    ('peer_topic_overcoming_self_doubt', 'Overcoming Self-Doubt', 19),
    ('peer_topic_personal_resilience', 'Personal Resilience', 20),
    ('peer_topic_managing_change', 'Managing Change', 21),
    ('peer_topic_personal_growth', 'Personal Growth', 22),
    ('peer_topic_lessons_learned', 'Lessons Learned', 23),
    ('peer_topic_failure_stories', 'Failure Stories', 24),
    ('peer_topic_success_stories', 'Success Stories', 25),
    ('peer_topic_founder_journeys', 'Founder Journeys', 26),
    ('peer_topic_peer_groups', 'Peer Groups', 27),
    ('peer_topic_networking', 'Networking', 28),
    ('peer_topic_other', 'Other', 29)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Accountability (accountability)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'accountability', 'Accountability', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'accountability'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('acc_subtype_business_goal_accountability', 'Business Goal Accountability', 1),
    ('acc_subtype_weekly_progress_check_in', 'Weekly Progress Check-In', 2),
    ('acc_subtype_startup_execution', 'Startup Execution', 3),
    ('acc_subtype_personal_productivity', 'Personal Productivity', 4),
    ('acc_subtype_habit_building', 'Habit Building', 5),
    ('acc_subtype_sales_accountability', 'Sales Accountability', 6),
    ('acc_subtype_marketing_accountability', 'Marketing Accountability', 7),
    ('acc_subtype_learning_accountability', 'Learning Accountability', 8),
    ('acc_subtype_team_project_accountability', 'Team / Project Accountability', 9),
    ('acc_subtype_other', 'Other', 10)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'accountability'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('acc_topic_business_milestones', 'Business Milestones', 1),
    ('acc_topic_revenue_goals', 'Revenue Goals', 2),
    ('acc_topic_growth_targets', 'Growth Targets', 3),
    ('acc_topic_strategic_goals', 'Strategic Goals', 4),
    ('acc_topic_weekly_planning', 'Weekly Planning', 5),
    ('acc_topic_progress_review', 'Progress Review', 6),
    ('acc_topic_action_items', 'Action Items', 7),
    ('acc_topic_follow_up', 'Follow-Up', 8),
    ('acc_topic_mvp_launch', 'MVP Launch', 9),
    ('acc_topic_product_development', 'Product Development', 10),
    ('acc_topic_customer_validation', 'Customer Validation', 11),
    ('acc_topic_business_setup', 'Business Setup', 12),
    ('acc_topic_time_management', 'Time Management', 13),
    ('acc_topic_daily_planning', 'Daily Planning', 14),
    ('acc_topic_focus_productivity', 'Focus & Productivity', 15),
    ('acc_topic_task_completion', 'Task Completion', 16),
    ('acc_topic_consistency', 'Consistency', 17),
    ('acc_topic_routine_building', 'Routine Building', 18),
    ('acc_topic_discipline', 'Discipline', 19),
    ('acc_topic_lead_generation_targets', 'Lead Generation Targets', 20),
    ('acc_topic_sales_calls', 'Sales Calls', 21),
    ('acc_topic_sales_follow_ups', 'Sales Follow-Ups', 22),
    ('acc_topic_conversion_goals', 'Conversion Goals', 23),
    ('acc_topic_content_publishing', 'Content Publishing', 24),
    ('acc_topic_campaign_execution', 'Campaign Execution', 25),
    ('acc_topic_marketing_calendar', 'Marketing Calendar', 26),
    ('acc_topic_skill_development', 'Skill Development', 27),
    ('acc_topic_course_completion', 'Course Completion', 28),
    ('acc_topic_reading_goals', 'Reading Goals', 29),
    ('acc_topic_project_milestones', 'Project Milestones', 30),
    ('acc_topic_team_deliverables', 'Team Deliverables', 31),
    ('acc_topic_deadline_management', 'Deadline Management', 32),
    ('acc_topic_execution_tracking', 'Execution Tracking', 33),
    ('acc_topic_other', 'Other', 34)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

-- ----------------------------------------------------
-- Category: Review / Feedback (review_feedback)
-- ----------------------------------------------------
INSERT INTO ask_types (id, flow_id, code, name, is_active, sort_order, created_at, updated_at)
SELECT gen_random_uuid(), f.id, 'review_feedback', 'Review / Feedback', true, 10, NOW(), NOW()
FROM ask_flows f WHERE f.code = 'help'
ON CONFLICT (flow_id, code) DO UPDATE SET is_active = true, updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'review_feedback'
JOIN ask_option_groups g ON g.code = 'help_subtype'
CROSS JOIN (VALUES
    ('rev_subtype_business_idea_review', 'Business Idea Review', 1),
    ('rev_subtype_business_plan_review', 'Business Plan Review', 2),
    ('rev_subtype_pitch_deck_review', 'Pitch Deck Review', 3),
    ('rev_subtype_product_review', 'Product Review', 4),
    ('rev_subtype_website_app_review', 'Website / App Review', 5),
    ('rev_subtype_marketing_review', 'Marketing Review', 6),
    ('rev_subtype_branding_review', 'Branding Review', 7),
    ('rev_subtype_sales_strategy_review', 'Sales Strategy Review', 8),
    ('rev_subtype_financial_plan_review', 'Financial Plan Review', 9),
    ('rev_subtype_proposal_document_review', 'Proposal / Document Review', 10),
    ('rev_subtype_presentation_review', 'Presentation Review', 11),
    ('rev_subtype_customer_experience_review', 'Customer Experience Review', 12),
    ('rev_subtype_strategy_review', 'Strategy Review', 13),
    ('rev_subtype_other', 'Other', 14)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();

INSERT INTO ask_options (id, option_group_id, flow_id, ask_type_id, code, label, sort_order, is_active, created_at, updated_at)
SELECT gen_random_uuid(), g.id, f.id, t.id, val.code, val.label, val.sort_order, true, NOW(), NOW()
FROM ask_flows f
JOIN ask_types t ON t.flow_id = f.id AND t.code = 'review_feedback'
JOIN ask_option_groups g ON g.code = 'help_topic'
CROSS JOIN (VALUES
    ('rev_topic_idea_validation', 'Idea Validation', 1),
    ('rev_topic_market_potential', 'Market Potential', 2),
    ('rev_topic_customer_problem', 'Customer Problem', 3),
    ('rev_topic_business_model', 'Business Model', 4),
    ('rev_topic_business_strategy', 'Business Strategy', 5),
    ('rev_topic_market_research', 'Market Research', 6),
    ('rev_topic_financial_projections', 'Financial Projections', 7),
    ('rev_topic_execution_plan', 'Execution Plan', 8),
    ('rev_topic_investor_readiness', 'Investor Readiness', 9),
    ('rev_topic_pitch_storytelling', 'Pitch Storytelling', 10),
    ('rev_topic_financial_slides', 'Financial Slides', 11),
    ('rev_topic_presentation_clarity', 'Presentation Clarity', 12),
    ('rev_topic_product_usability', 'Product Usability', 13),
    ('rev_topic_product_features', 'Product Features', 14),
    ('rev_topic_product_market_fit', 'Product-Market Fit', 15),
    ('rev_topic_user_experience', 'User Experience', 16),
    ('rev_topic_website_design', 'Website Design', 17),
    ('rev_topic_website_navigation', 'Website Navigation', 18),
    ('rev_topic_website_performance', 'Website Performance', 19),
    ('rev_topic_app_functionality', 'App Functionality', 20),
    ('rev_topic_campaign_effectiveness', 'Campaign Effectiveness', 21),
    ('rev_topic_content_quality', 'Content Quality', 22),
    ('rev_topic_target_audience', 'Target Audience', 23),
    ('rev_topic_marketing_strategy', 'Marketing Strategy', 24),
    ('rev_topic_brand_identity', 'Brand Identity', 25),
    ('rev_topic_logo_design', 'Logo & Design', 26),
    ('rev_topic_brand_messaging', 'Brand Messaging', 27),
    ('rev_topic_brand_positioning', 'Brand Positioning', 28),
    ('rev_topic_sales_process', 'Sales Process', 29),
    ('rev_topic_pricing_strategy', 'Pricing Strategy', 30),
    ('rev_topic_customer_conversion', 'Customer Conversion', 31),
    ('rev_topic_budget_review', 'Budget Review', 32),
    ('rev_topic_cash_flow', 'Cash Flow', 33),
    ('rev_topic_revenue_forecast', 'Revenue Forecast', 34),
    ('rev_topic_document_structure', 'Document Structure', 35),
    ('rev_topic_professional_presentation', 'Professional Presentation', 36),
    ('rev_topic_customer_journey', 'Customer Journey', 37),
    ('rev_topic_customer_retention', 'Customer Retention', 38),
    ('rev_topic_competitive_positioning', 'Competitive Positioning', 39),
    ('rev_topic_strategic_priorities', 'Strategic Priorities', 40),
    ('rev_topic_other', 'Other', 41)
) AS val(code, label, sort_order)
WHERE f.code = 'help'
ON CONFLICT (option_group_id, code) DO UPDATE SET
    ask_type_id = EXCLUDED.ask_type_id,
    flow_id = EXCLUDED.flow_id,
    label = EXCLUDED.label,
    sort_order = EXCLUDED.sort_order,
    is_active = true,
    deleted_at = NULL,
    updated_at = NOW();
