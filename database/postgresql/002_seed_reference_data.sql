BEGIN;

INSERT INTO roles (code, label) VALUES
    ('user', 'User'),
    ('employee', 'Employee'),
    ('administrator', 'Administrator')
ON CONFLICT (code) DO NOTHING;

INSERT INTO themes (name, slug) VALUES
    ('Classic', 'classic'),
    ('Christmas', 'christmas'),
    ('Easter', 'easter'),
    ('Event', 'event')
ON CONFLICT (slug) DO NOTHING;

INSERT INTO diets (name, slug) VALUES
    ('Classic', 'classic'),
    ('Vegetarian', 'vegetarian'),
    ('Vegan', 'vegan'),
    ('Gluten-free', 'gluten-free')
ON CONFLICT (slug) DO NOTHING;

INSERT INTO dish_types (code, label, display_order) VALUES
    ('starter', 'Starter', 1),
    ('main_course', 'Main course', 2),
    ('dessert', 'Dessert', 3),
    ('side', 'Side dish', 4)
ON CONFLICT (code) DO NOTHING;

INSERT INTO allergens (code, name) VALUES
    ('gluten', 'Cereals containing gluten'),
    ('crustaceans', 'Crustaceans'),
    ('eggs', 'Eggs'),
    ('fish', 'Fish'),
    ('peanuts', 'Peanuts'),
    ('soybeans', 'Soybeans'),
    ('milk', 'Milk'),
    ('nuts', 'Nuts'),
    ('celery', 'Celery'),
    ('mustard', 'Mustard'),
    ('sesame', 'Sesame seeds'),
    ('sulphites', 'Sulphur dioxide and sulphites'),
    ('lupin', 'Lupin'),
    ('molluscs', 'Molluscs')
ON CONFLICT (code) DO NOTHING;

INSERT INTO order_statuses (code, label, display_order, is_terminal) VALUES
    ('pending', 'Pending', 1, FALSE),
    ('accepted', 'Accepted', 2, FALSE),
    ('in_preparation', 'In preparation', 3, FALSE),
    ('out_for_delivery', 'Out for delivery', 4, FALSE),
    ('delivered', 'Delivered', 5, FALSE),
    ('awaiting_equipment_return', 'Awaiting equipment return', 6, FALSE),
    ('completed', 'Completed', 7, TRUE),
    ('cancelled', 'Cancelled', 8, TRUE)
ON CONFLICT (code) DO NOTHING;

COMMIT;

