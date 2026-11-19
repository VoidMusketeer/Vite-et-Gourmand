BEGIN;

CREATE EXTENSION IF NOT EXISTS citext;
-- gen_random_uuid() is built into PostgreSQL 16.

CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- 1. Application roles
CREATE TABLE roles (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL,
    CONSTRAINT roles_code_format CHECK (code ~ '^[a-z_]+$')
);

-- 2. Customers, employees and administrators
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id SMALLINT NOT NULL REFERENCES roles(id),
    email CITEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    postal_code VARCHAR(20) NOT NULL,
    city VARCHAR(120) NOT NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'FR',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    email_verified_at TIMESTAMPTZ,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_email_not_blank CHECK (length(trim(email::text)) > 3),
    CONSTRAINT users_password_hash_not_blank CHECK (length(password_hash) >= 20)
);

-- 3. Single-use reset links; only a token hash is stored
CREATE TABLE password_reset_tokens (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT password_reset_expiry CHECK (expires_at > created_at)
);

-- 4. Menu themes
CREATE TABLE themes (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

-- 5. Dietary classifications
CREATE TABLE diets (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
);

-- 6. Menus
CREATE TABLE menus (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    theme_id SMALLINT REFERENCES themes(id) ON DELETE SET NULL,
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    conditions TEXT NOT NULL,
    minimum_people INTEGER NOT NULL,
    minimum_price NUMERIC(10,2) NOT NULL,
    available_stock INTEGER NOT NULL DEFAULT 0,
    order_lead_time_hours INTEGER NOT NULL DEFAULT 48,
    publication_status VARCHAR(20) NOT NULL DEFAULT 'draft',
    available_from TIMESTAMPTZ,
    available_until TIMESTAMPTZ,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    updated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT menus_minimum_people_positive CHECK (minimum_people > 0),
    CONSTRAINT menus_minimum_price_nonnegative CHECK (minimum_price >= 0),
    CONSTRAINT menus_stock_nonnegative CHECK (available_stock >= 0),
    CONSTRAINT menus_lead_time_nonnegative CHECK (order_lead_time_hours >= 0),
    CONSTRAINT menus_status_allowed CHECK (publication_status IN ('draft', 'published', 'unpublished', 'archived')),
    CONSTRAINT menus_availability_order CHECK (available_until IS NULL OR available_from IS NULL OR available_until > available_from)
);

-- 7. Many-to-many menu/diet relation
CREATE TABLE menu_diets (
    menu_id UUID NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    diet_id SMALLINT NOT NULL REFERENCES diets(id) ON DELETE RESTRICT,
    PRIMARY KEY (menu_id, diet_id)
);

-- 8. Menu gallery
CREATE TABLE menu_images (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    menu_id UUID NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    url TEXT NOT NULL,
    alt_text VARCHAR(255) NOT NULL,
    display_order SMALLINT NOT NULL DEFAULT 0,
    is_cover BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT menu_images_order_nonnegative CHECK (display_order >= 0)
);

CREATE UNIQUE INDEX menu_images_one_cover_per_menu
    ON menu_images(menu_id) WHERE is_cover;

-- 9. Starter, main course, dessert, etc.
CREATE TABLE dish_types (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    label VARCHAR(80) NOT NULL,
    display_order SMALLINT NOT NULL DEFAULT 0
);

-- 10. Reusable dishes
CREATE TABLE dishes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    dish_type_id SMALLINT NOT NULL REFERENCES dish_types(id),
    name VARCHAR(160) NOT NULL,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 11. Many-to-many menu/dish relation
CREATE TABLE menu_dishes (
    menu_id UUID NOT NULL REFERENCES menus(id) ON DELETE CASCADE,
    dish_id UUID NOT NULL REFERENCES dishes(id) ON DELETE RESTRICT,
    display_order SMALLINT NOT NULL DEFAULT 0,
    is_optional BOOLEAN NOT NULL DEFAULT FALSE,
    additional_price NUMERIC(10,2) NOT NULL DEFAULT 0,
    PRIMARY KEY (menu_id, dish_id),
    CONSTRAINT menu_dishes_order_nonnegative CHECK (display_order >= 0),
    CONSTRAINT menu_dishes_price_nonnegative CHECK (additional_price >= 0)
);

-- 12. Reference list of allergens
CREATE TABLE allergens (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL UNIQUE
);

-- 13. Many-to-many dish/allergen relation
CREATE TABLE dish_allergens (
    dish_id UUID NOT NULL REFERENCES dishes(id) ON DELETE CASCADE,
    allergen_id SMALLINT NOT NULL REFERENCES allergens(id) ON DELETE RESTRICT,
    PRIMARY KEY (dish_id, allergen_id)
);

-- 14. Stable status reference data
CREATE TABLE order_statuses (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL,
    display_order SMALLINT NOT NULL,
    is_terminal BOOLEAN NOT NULL DEFAULT FALSE
);

-- 15. Order header and delivery details
CREATE TABLE orders (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    reference VARCHAR(30) NOT NULL UNIQUE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    current_status_id SMALLINT NOT NULL REFERENCES order_statuses(id),
    service_date DATE NOT NULL,
    delivery_time TIME NOT NULL,
    delivery_address_line1 VARCHAR(255) NOT NULL,
    delivery_address_line2 VARCHAR(255),
    delivery_postal_code VARCHAR(20) NOT NULL,
    delivery_city VARCHAR(120) NOT NULL,
    delivery_country_code CHAR(2) NOT NULL DEFAULT 'FR',
    delivery_distance_km NUMERIC(8,2) NOT NULL DEFAULT 0,
    menu_subtotal NUMERIC(10,2) NOT NULL,
    discount_rate NUMERIC(5,4) NOT NULL DEFAULT 0,
    discount_amount NUMERIC(10,2) NOT NULL DEFAULT 0,
    delivery_fee NUMERIC(10,2) NOT NULL DEFAULT 0,
    total_amount NUMERIC(10,2) NOT NULL,
    customer_note TEXT,
    cancellation_reason TEXT,
    cancellation_contact_method VARCHAR(20),
    cancelled_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT orders_reference_format CHECK (reference ~ '^VG-[0-9]{4,}$'),
    CONSTRAINT orders_distance_nonnegative CHECK (delivery_distance_km >= 0),
    CONSTRAINT orders_amounts_nonnegative CHECK (
        menu_subtotal >= 0 AND discount_amount >= 0 AND delivery_fee >= 0 AND total_amount >= 0
    ),
    CONSTRAINT orders_discount_rate_range CHECK (discount_rate BETWEEN 0 AND 1 AND discount_amount <= menu_subtotal),
    CONSTRAINT orders_total_formula CHECK (
        total_amount = round(menu_subtotal - discount_amount + delivery_fee, 2)
    ),
    CONSTRAINT orders_cancellation_contact_method CHECK (
        cancellation_contact_method IS NULL OR cancellation_contact_method IN ('phone', 'email')
    )
);

-- 16. Ordered menu snapshot. The subject currently allows one menu per order.
CREATE TABLE order_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    menu_id UUID NOT NULL REFERENCES menus(id) ON DELETE RESTRICT,
    menu_title VARCHAR(160) NOT NULL,
    people_count INTEGER NOT NULL,
    minimum_people_snapshot INTEGER NOT NULL,
    unit_price_per_person NUMERIC(10,2) NOT NULL,
    line_total NUMERIC(10,2) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT order_items_one_menu_per_order UNIQUE (order_id),
    CONSTRAINT order_items_people_minimum CHECK (people_count >= minimum_people_snapshot AND minimum_people_snapshot > 0),
    CONSTRAINT order_items_prices_nonnegative CHECK (unit_price_per_person >= 0 AND line_total >= 0),
    CONSTRAINT order_items_total_formula CHECK (line_total = round(unit_price_per_person * people_count, 2))
);

-- 17. Complete order tracking timeline
CREATE TABLE order_status_history (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    status_id SMALLINT NOT NULL REFERENCES order_statuses(id),
    changed_by UUID REFERENCES users(id) ON DELETE SET NULL,
    public_comment TEXT,
    internal_comment TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 18. One review per completed order
CREATE TABLE reviews (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
    -- Review author is the customer linked through orders.user_id.
    rating SMALLINT NOT NULL,
    comment TEXT NOT NULL,
    moderation_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    moderated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    moderated_at TIMESTAMPTZ,
    rejection_reason TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT reviews_rating_range CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT reviews_moderation_status CHECK (moderation_status IN ('pending', 'approved', 'rejected'))
);

-- 19. Footer opening hours
CREATE TABLE opening_hours (
    id SMALLINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    day_of_week SMALLINT NOT NULL UNIQUE,
    opens_at TIME,
    closes_at TIME,
    is_closed BOOLEAN NOT NULL DEFAULT FALSE,
    updated_by UUID REFERENCES users(id) ON DELETE SET NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT opening_hours_day_range CHECK (day_of_week BETWEEN 1 AND 7),
    CONSTRAINT opening_hours_consistency CHECK (
        (is_closed AND opens_at IS NULL AND closes_at IS NULL)
        OR
        (NOT is_closed AND opens_at IS NOT NULL AND closes_at IS NOT NULL AND closes_at > opens_at)
    )
);

-- 20. Public contact form submissions
CREATE TABLE contact_messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sender_email CITEXT NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    processing_status VARCHAR(20) NOT NULL DEFAULT 'new',
    handled_by UUID REFERENCES users(id) ON DELETE SET NULL,
    handled_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT contact_messages_status CHECK (processing_status IN ('new', 'in_progress', 'answered', 'closed'))
);

-- Useful lookup and filtering indexes
CREATE INDEX users_role_id_idx ON users(role_id);
CREATE INDEX menus_public_listing_idx ON menus(publication_status, available_from, available_until);
CREATE INDEX menus_theme_id_idx ON menus(theme_id);
CREATE INDEX menu_dishes_dish_id_idx ON menu_dishes(dish_id);
CREATE INDEX dish_allergens_allergen_id_idx ON dish_allergens(allergen_id);
CREATE INDEX orders_user_created_idx ON orders(user_id, created_at DESC);
CREATE INDEX orders_status_service_date_idx ON orders(current_status_id, service_date);
CREATE INDEX order_status_history_order_created_idx ON order_status_history(order_id, created_at);
CREATE INDEX reviews_moderation_created_idx ON reviews(moderation_status, created_at);
CREATE INDEX contact_messages_status_created_idx ON contact_messages(processing_status, created_at);
CREATE INDEX password_reset_tokens_expiry_idx ON password_reset_tokens(expires_at) WHERE used_at IS NULL;

-- Automatically maintain updated_at fields
CREATE TRIGGER users_set_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER menus_set_updated_at BEFORE UPDATE ON menus
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER dishes_set_updated_at BEFORE UPDATE ON dishes
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER orders_set_updated_at BEFORE UPDATE ON orders
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER reviews_set_updated_at BEFORE UPDATE ON reviews
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE TRIGGER opening_hours_set_updated_at BEFORE UPDATE ON opening_hours
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();



COMMIT;
