// Fonction pour récupérer le prix du produit sélectionné
    function fetchPrice() {
        const productSelect = document.getElementById("productSelect");
        const selectedProductId = productSelect.value;

        if (selectedProductId) {
            const url = `../PUBLIC/getprice.php?categorie=${selectedProductId}`;
            console.log("URL appelée :", url);

            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error("Erreur HTTP : " + response.status);
                    }
                    return response.json(); // Parse la réponse JSON
                })
                .then(data => {
                    console.log("Données reçues :", data);

                    if (data.success) {
                        // Formatage du prix
                        const formattedPrice = Number(data.prix_vente).toLocaleString('en-US');
                        const rawPrice = Number(data.prix_vente); // Prix brut pour le calcul

                        // Mise à jour du champ productPrice
                        document.getElementById("productPrice").value = formattedPrice;

                        // Mise à jour de l'attribut data-price pour le calcul
                        document.getElementById("productPrice").setAttribute("data-price", rawPrice);

                        // Mise à jour de la somme
                        updateSum();
                    } else {
                        document.getElementById("productPrice").value = "Non disponible";
                        document.getElementById("productPrice").setAttribute("data-price", "0");
                        updateSum();
                    }
                })
                .catch(error => {
                    console.error("Erreur lors de la récupération du prix :", error);
                    document.getElementById("productPrice").value = "Erreur";
                    document.getElementById("productPrice").setAttribute("data-price", "0");
                    updateSum();
                });
        } else {
            // Réinitialise le champ si aucune catégorie n'est sélectionnée
            document.getElementById("productPrice").value = "";
            document.getElementById("productPrice").setAttribute("data-price", "0");
            updateSum();
        }
    }

    // Fonction pour calculer et afficher la somme
    function updateSum() {
        // Récupérer les valeurs des champs Prix 1 et Product Price
        const prix1 = parseFloat(document.getElementById("prixmonture").getAttribute("data-price")) || 0;
        const productPrice = parseFloat(document.getElementById("productPrice").getAttribute("data-price")) || 0;

        // Calculer la somme
        const total = prix1 + productPrice;

        // Afficher la somme formatée
        document.getElementById("totalPrice").value = Number(total).toLocaleString('en-US');
    }

    // Initialiser les champs data-price pour le calcul au chargement
    window.addEventListener("load", () => {
        const prix1Field = document.getElementById("prixmonture");
        const productPriceField = document.getElementById("productPrice");

        // Initialisation des attributs data-price
        prix1Field.setAttribute("data-price", prix1Field.value.replace(/,/g, "") || "0");
        productPriceField.setAttribute("data-price", "0");

        // Calcul initial de la somme
        updateSum();
    });


    // Fonction pour mettre à jour les motifs en fonction du service sélectionné
    function updateMotifs() {
        const serviceId = document.getElementById("serviceSelect").value;
        const motifSelect = document.getElementById("motifSelect");

        if (!serviceId) {
            motifSelect.innerHTML = '<option value=""> ------ Choisir le motif ----- </option>';
            return;
        }

        fetch(`../PUBLIC/getMotifs.php?service=${serviceId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erreur HTTP : " + response.status);
                }
                return response.json();
            })
            .then(data => {
                motifSelect.innerHTML = '<option value=""> ------ Choisir le motif ----- </option>';
                if (data.success) {
                    data.motifs.forEach(motif => {
                        const option = document.createElement("option");
                        option.value = motif.id;
                        option.textContent = motif.nom;
                        motifSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error("Erreur lors de la récupération des motifs :", error);
                motifSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
            });
    }

    // fonction des motifs pour le rendez-vous

 /*   function updateMotifs() {
        const serviceId = document.getElementById("serviceSelect").value;
        const motifSelect = document.getElementById("motifSelect");

        if (!serviceId) {
            motifSelect.innerHTML = '<option value=""> ------ Choisir le motif ----- </option>';
            return;
        }

        fetch(`../PUBLIC/getMotifs.php?service=${serviceId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error("Erreur HTTP : " + response.status);
                }
                return response.json();
            })
            .then(data => {
                motifSelect.innerHTML = '<option value=""> ------ Choisir le motif ----- </option>';
                if (data.success) {
                    data.motifs.forEach(motif => {
                        const option = document.createElement("option");
                        option.value = motif.id;
                        option.textContent = motif.nom;
                        motifSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error("Erreur lors de la récupération des motifs :", error);
                motifSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
            });
    } */

    // Fonction pour mettre à jour les motifs en fonction du service sélectionné
    

    // Fonction pour récupérer le prix du motif sélectionné
    function fetchMotifPrice() {
    const motifId = document.getElementById("motifSelect").value;
    console.log("Motif sélectionné :", motifId); // Vérifier l'ID sélectionné

    if (!motifId) {
        document.getElementById("productPrice").value = "";
        document.getElementById("hiddenMotifId").value = "";
        return;
    }

    fetch(`../PUBLIC/getMotifPrice.php?motif=${motifId}`)
        .then(response => {
            console.log("Réponse brute :", response); // Debug : Vérifier la réponse brute
            if (!response.ok) {
                throw new Error("Erreur HTTP : " + response.status);
            }
            return response.json(); // Parse la réponse JSON
        })
        .then(data => {
            console.log("Données reçues :", data); // Debug : Afficher les données reçues
            if (data.success) {
                // Affiche le prix et met à jour le champ caché
                const formattedPrice = Number(data.montant).toLocaleString('en-US');
                document.getElementById("productPrice").value = formattedPrice;
                document.getElementById("hiddenMotifId").value = motifId; // Enregistre l'ID du motif pour l'envoi
            } else {
                document.getElementById("productPrice").value = "Non disponible";
                document.getElementById("hiddenMotifId").value = "";
            }
        })
        .catch(error => {
            console.error("Erreur lors de la récupération du prix :", error);
            document.getElementById("productPrice").value = "Erreur";
            document.getElementById("hiddenMotifId").value = "";
        });
}

function soldeCompte() {
    const compteId = document.getElementById("compteSelect").value;
    console.log("Compte sélectionné :", compteId); // Debug: Vérifier l'ID sélectionné

    if (!compteId) {
        document.getElementById("soldeCompte").value = "";
        return;
    }

    fetch(`../PUBLIC/getamount.php?type=compte&id=${compteId}`)
        .then(response => {
            if (!response.ok) throw new Error("Erreur HTTP : " + response.status);
            return response.json(); 
        })
        .then(data => {
            if (data.success) {
                const formattedPrice = Number(data.solde).toLocaleString('en-US');
                document.getElementById("soldeCompte").value = formattedPrice;
            } else {
                document.getElementById("soldeCompte").value = "Non disponible";
            }
        })
        .catch(error => {
            console.error("Erreur lors de la récupération du solde :", error);
            document.getElementById("soldeCompte").value = "Erreur";
        });
}

function soldeBudget() {
    const budgetId = document.getElementById("budgetSelect").value;
    console.log("Budget sélectionné :", budgetId); // Debug: Vérifier l'ID sélectionné

    if (!budgetId) {
        document.getElementById("soldeBudget").value = "";
        return;
    }

    fetch(`../PUBLIC/getamount.php?type=budget&id=${budgetId}`)
        .then(response => {
            if (!response.ok) throw new Error("Erreur HTTP : " + response.status);
            return response.json(); 
        })
        .then(data => {
            if (data.success) {
                const formattedPrice = Number(data.solde).toLocaleString('en-US');
                document.getElementById("soldeBudget").value = formattedPrice;
            } else {
                document.getElementById("soldeBudget").value = "Non disponible";
            }
        })
        .catch(error => {
            console.error("Erreur lors de la récupération du solde :", error);
            document.getElementById("soldeBudget").value = "Erreur";
        });
}

// ---------- Here begin other thing ----------------

// Fonction pour mettre à jour les quartiers en fonction de la ville sélectionné
function updateQuartier() {
    const villeId = document.getElementById("villeSelect").value;
    const quartierSelect = document.getElementById("quartierSelect");
    
    // Réinitialiser le champ
    quartierSelect.innerHTML = '<option value=""> ------ Choisir le quartier ----- </option>';
    document.getElementById("hiddenquartierId").value = "";

    if (!villeId) {
        return;
    }

    fetch(`../PUBLIC/getQuartiers.php?ville=${villeId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error("Erreur HTTP : " + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                data.quartier.forEach(quartier => {
                    const option = document.createElement("option");
                    option.value = quartier.id;
                    option.textContent = quartier.nom;
                    quartierSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error("Erreur lors de la récupération des quartiers :", error);
            quartierSelect.innerHTML = '<option value="">Erreur lors du chargement</option>';
        });
}

// Mettre à jour hiddenquartierId lorsqu'un quartier est sélectionné
document.getElementById("quartierSelect").addEventListener("change", function () {
    document.getElementById("hiddenquartierId").value = this.value;
});

// fonction pour recuperer et afficher la quantité du bon de livraison

function updateQTE() {
    const blId = document.getElementById("nolivraison").value;
    const qteBLInput = document.getElementById("qteBL");
    if (!blId) {
        qteBLInput.value = "";
        return;
    }
    fetch(`../PUBLIC/getQTEBL.php?bl=${encodeURIComponent(blId)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error("Erreur HTTP : " + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                qteBLInput.value = Number(data.quantite).toLocaleString('fr-FR');
            } else {
                qteBLInput.value = "Non disponible";
            }
        })
        .catch(error => {
            console.error("Erreur lors de la récupération de la quantité :", error);
            qteBLInput.value = "Erreur";
        });
}

// Génère les créneaux de 30min entre 08:00 et 18:00
    function genererCreneaux(date, medecinId, rdvExclu) {
        if (!date || !medecinId) return;
        
        // Construire l'URL avec les paramètres
        var url = '../public/getCreneaux.php?date=' + encodeURIComponent(date) + '&medecin=' + encodeURIComponent(medecinId) + '&format=simple';
        
        // Ajouter le paramètre rdv_exclu si fourni (pour les mises à jour)
        if (rdvExclu && rdvExclu > 0) {
            url += '&rdv_exclu=' + encodeURIComponent(rdvExclu);
        }
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Créneaux reçus :', data); // Log pour vérifier les données reçues
                var select = document.getElementById('creneauSelect');
                
                if (!select) {
                    console.error('Élément creneauSelect introuvable');
                    return;
                }
                
                select.innerHTML = '<option value="">-- Choisir un créneau disponible --</option>';
                
                if (!Array.isArray(data) || data.length === 0) {
                    var optionAucun = document.createElement('option');
                    optionAucun.value = '';
                    optionAucun.textContent = 'Aucun créneau libre';
                    optionAucun.disabled = true;
                    select.appendChild(optionAucun);
                    return;
                }
                
                data.forEach(function(creneau) {
                    var opt = document.createElement('option');
                    
                    // Le créneau vient au format ISO: 2025-10-01T08:00:00
                    var timeValue = creneau; // Valeur complète pour le serveur
                    var display = creneau;   // Affichage pour l'utilisateur
                    
                    try {
                        if (creneau.indexOf('T') !== -1) {
                            // Format ISO: 2025-10-01T08:00:00
                            var timePart = creneau.split('T')[1]; // Extraire "08:00:00"
                            display = timePart.slice(0, 5); // Afficher "08:00"
                        } else if (creneau.indexOf(' ') !== -1) {
                            // Format avec espace: 2025-10-01 08:00:00
                            var timePart = creneau.split(' ')[1]; // Extraire "08:00:00"
                            display = timePart.slice(0, 5); // Afficher "08:00"
                        } else {
                            // Format heure seule: 08:00:00
                            display = creneau.slice(0, 5); // Afficher "08:00"
                        }
                    } catch (e) {
                        console.warn('Erreur parsing créneau:', creneau, e);
                        display = creneau;
                    }
                    
                    opt.value = creneau; // Valeur complète envoyée au serveur
                    opt.textContent = display; // Texte affiché à l'utilisateur (HH:MM)
                    select.appendChild(opt);
                });
                
                // Activer le select si désactivé
                select.disabled = false;
                
                // Déclencher l'événement change pour les plugins comme Select2
                try { 
                    if (window.jQuery && jQuery(select).data('select2')) {
                        jQuery(select).trigger('change'); 
                    }
                } catch(e) {
                    console.log('Select2 non disponible ou erreur:', e);
                }
            })
            .catch(error => {
                console.error('Erreur lors de la récupération des créneaux:', error);
                var select = document.getElementById('creneauSelect');
                if (select) {
                    select.innerHTML = '<option value="">Erreur de chargement</option>';
                    select.disabled = true;
                }
            });
    }

    // Fonction globale pour mettre à jour les créneaux (utilisable dans toutes les pages)
    function updateCreneauxGlobal(rdvExclu) {
        var medecinSelect = document.querySelector('select[name="medecin"]') || document.getElementById('medecinSelect');
        var dateInput = document.getElementById('dateRdvInput');
        var creneauSelect = document.getElementById('creneauSelect');

        if (!medecinSelect || !dateInput || !creneauSelect) {
            console.error('Un ou plusieurs éléments requis sont introuvables dans le DOM pour les créneaux.');
            return;
        }

        var medecin = medecinSelect.value;
        var date = dateInput.value;

        if (date && medecin) {
            genererCreneaux(date, medecin, rdvExclu);
        } else {
            creneauSelect.innerHTML = '<option value="">-- Choisir un créneau disponible --</option>';
            creneauSelect.disabled = true;
        }
    }

    // Sur changement de date ou de médecin, on recharge les créneaux
    document.addEventListener('DOMContentLoaded', function() {
        var medecinSelect = document.querySelector('select[name="medecin"]') || document.getElementById('medecinSelect');
        var dateInput = document.getElementById('dateRdvInput');
        var creneauSelect = document.getElementById('creneauSelect');

        if (!medecinSelect || !dateInput || !creneauSelect) {
            // Pas d'erreur si les éléments ne sont pas trouvés (toutes les pages n'ont pas ces champs)
            return;
        }

        // Fonction locale pour cette page
        function updateCreneaux() {
            updateCreneauxGlobal(); // Utiliser la fonction globale sans exclusion pour ajout
        }

        dateInput.addEventListener('change', updateCreneaux);
        medecinSelect.addEventListener('change', updateCreneaux);
        
        // Initialiser si médecin et date déjà sélectionnés
        if (dateInput.value && medecinSelect.value) {
            updateCreneaux();
        }
    });
    

    // fonction pour mettre à jour les médecins en fonction du service sélectionné

 // Recuperer uniquement les date supérieur à aujourd'hui
    // Empêcher la sélection des dates passées
    // Désactiver la saisie manuelle pour éviter les erreurs de format  

    document.addEventListener("DOMContentLoaded", function () {
        const input = document.getElementById("dateRdvInput");
        if (input) {
            let d = new Date();
            d.setDate(d.getDate() + 1); // Demain
            input.min = d.toISOString().split("T")[0];
        }
    });

    
    /* Add here all your JS customizations */