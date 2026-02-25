-- Ajout des colonnes de pièces jointes dans la table employes
-- Date: 2026-02-17

ALTER TABLE employes
    ADD COLUMN piece_identite VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN cv VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN diplome VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN extrait_naissance VARCHAR(255) NULL DEFAULT NULL;
