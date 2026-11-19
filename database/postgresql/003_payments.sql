BEGIN;

-- One order can have several payment attempts. Amounts are in currency minor
-- units (EUR cents); card details belong exclusively to the payment provider.
CREATE TABLE payments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id UUID NOT NULL REFERENCES orders(id) ON DELETE RESTRICT,
    provider VARCHAR(50) NOT NULL,
    provider_reference VARCHAR(255),
    idempotency_key UUID NOT NULL UNIQUE,
    amount_minor BIGINT NOT NULL CHECK (amount_minor > 0),
    currency CHAR(3) NOT NULL DEFAULT 'EUR' CHECK (currency ~ '^[A-Z]{3}$'),
    status VARCHAR(20) NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'processing', 'succeeded', 'failed', 'cancelled', 'partially_refunded', 'refunded')),
    refunded_amount_minor BIGINT NOT NULL DEFAULT 0
        CHECK (refunded_amount_minor >= 0 AND refunded_amount_minor <= amount_minor),
    paid_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (provider, provider_reference)
);
CREATE INDEX payments_order_created_idx ON payments(order_id, created_at);
CREATE TRIGGER payments_set_updated_at BEFORE UPDATE ON payments
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();

-- Deduplicates provider webhook deliveries. Insert and apply the payment/order
-- changes in one transaction, only after verifying the provider signature.
CREATE TABLE payment_events (
    id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    payment_id UUID NOT NULL REFERENCES payments(id) ON DELETE RESTRICT,
    provider_event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    processed_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (payment_id, provider_event_id)
);

CREATE INDEX menu_diets_diet_idx ON menu_diets(diet_id);
CREATE INDEX password_reset_tokens_user_idx ON password_reset_tokens(user_id);

COMMIT;
