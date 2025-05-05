CREATE TABLE fe_users
(
    date_of_birth       int(11) DEFAULT '0' NOT NULL,
    gender              int(11) DEFAULT '0'  NOT NULL,
    iban                varchar(255) DEFAULT '' NOT NULL,
    bic                 varchar(255) DEFAULT '' NOT NULL,
    privacy_accepted_at int(11) DEFAULT '0' NOT NULL,
    member_since        int(11) DEFAULT '0' NOT NULL,
    member_until        int(11) DEFAULT '0' NOT NULL,
    membership          int(11) DEFAULT '0' NOT NULL,
    membership_status   int(11) DEFAULT '0' NOT NULL,
    payments            int(11) DEFAULT '0' NOT NULL,
    notes               text         DEFAULT '' NOT NULL,
);

CREATE TABLE tx_membermanagement_membership
(
    title       varchar(255)   DEFAULT ''     NOT NULL,
    description text           DEFAULT ''     NOT NULL,
    price       decimal(10, 2) DEFAULT '0.00' NOT NULL,
);

CREATE TABLE tx_membermanagement_payment
(
    member  int(11) DEFAULT '0' NOT NULL,
    paid_at int(11) DEFAULT '0' NOT NULL,
    amount  decimal(10, 2) DEFAULT '0.00' NOT NULL,
);
