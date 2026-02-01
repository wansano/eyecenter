-- Table des notes de service (changement de poste)
CREATE TABLE IF NOT EXISTS notes_service (
    id_note INT AUTO_INCREMENT PRIMARY KEY,
    id_employe INT NOT NULL,
    ancien_poste VARCHAR(100) NOT NULL,
    nouveau_poste VARCHAR(100) NOT NULL,
    type_changement ENUM('definitif','temporaire') NOT NULL DEFAULT 'definitif',
    date_debut DATE NOT NULL,
    date_fin DATE DEFAULT NULL,
    motif TEXT,
    signataire_nom VARCHAR(100),
    signataire_fonction VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (id_employe) REFERENCES employes(id_employe)
);
