-- ============================================================================
-- Mfano Bora Resources Portal - Database Schema
-- Owner: Developer 3 (Data Modeler) / Developer 2 (Backend Architect)
-- ============================================================================

-- Enable UUID extension for unique identifier generation (kept for future use)
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- 1. Categories Table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 2. Sub-Categories Table
CREATE TABLE sub_categories (
    id SERIAL PRIMARY KEY,
    category_id INT NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 3. Resources Table (Core Asset Store)
CREATE TABLE resources (
    id SERIAL PRIMARY KEY,
    sub_category_id INT NOT NULL REFERENCES sub_categories(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    file_url TEXT NOT NULL,
    file_size_kb INT DEFAULT 0,
    download_count INT DEFAULT 0,
    is_featured BOOLEAN DEFAULT FALSE,
    is_published BOOLEAN DEFAULT TRUE,
    publish_date DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for lightning-fast queries and searching
CREATE INDEX idx_sub_categories_category ON sub_categories(category_id);
CREATE INDEX idx_resources_sub_category ON resources(sub_category_id);
CREATE INDEX idx_resources_published ON resources(is_published);
CREATE INDEX idx_resources_featured ON resources(is_featured);
CREATE INDEX idx_resources_title_search
    ON resources USING gin(to_tsvector('english', title || ' ' || description));
