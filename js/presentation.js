/*
 * js/presentation.js
 * ---------------------------------------------------------------
 * Filtres et tris dynamiques de la page de présentation des plats
 * (phase 3 du sujet : "AJAX obligatoire pour les filtres").
 *
 * Découpage clair :
 *   - FILTRES (catégorie / régime / prix / recherche)
 *       → envoyés en AJAX vers api/filtrer_plats.php qui renvoie
 *         la liste de plats correspondante en JSON. On reconstruit
 *         ensuite la grille HTML côté client.
 *   - TRI (prix croissant/décroissant, nom A-Z/Z-A)
 *       → effectué côté client sur les données déjà récupérées
 *         (comme l'exige le sujet).
 *
 * Pour le bouton "+ Ajouter" sur chaque plat, on laisse le
 * formulaire HTML standard (POST classique) : ce point n'est pas
 * dans le périmètre des filtres AJAX. La page se rechargera donc
 * une fois quand on ajoute, ce qui est cohérent avec le code PHP
 * existant.
 *
 * Dépendances : aucune (vanilla JS, fetch + async/await).
 */

document.addEventListener('DOMContentLoaded', () => {
    const form          = document.querySelector('form.recherche-container');
    const grille        = document.getElementById('grille-plats');
    const messageAucun  = document.getElementById('message-aucun-plat');
    const loader        = document.getElementById('ajax-loader');
    const btnReset      = document.getElementById('btn-reset-filtres');
    const champTri      = document.getElementById('tri');

    if (!form || !grille) return;   // page différente, on sort

    // === Cache des plats actuellement affichés (utilisé pour tri) //
    // Au chargement, on extrait les données depuis le HTML rendu
    // par PHP pour pouvoir trier sans refaire un appel AJAX.
    let platsCourants = lirePlatsDepuisDom();

    // === Empêche la soumission classique du formulaire =========== //
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        chargerFiltres();
    });

    // === Bascule en mode dynamique : déclenche à chaque changement //
    form.querySelectorAll('select').forEach((select) => {
        if (select.id === 'tri') {
            select.addEventListener('change', appliquerTri);
        } else {
            select.addEventListener('change', () => chargerFiltres());
        }
    });

    // Saisie texte : déclenche avec un petit délai (300 ms) pour
    // éviter une requête à chaque touche.
    const champRecherche = form.querySelector('input[name="recherche"]');
    if (champRecherche) {
        let timer = null;
        champRecherche.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(chargerFiltres, 300);
        });
    }

    // Reset : on intercepte pour ne pas recharger la page
    if (btnReset) {
        btnReset.addEventListener('click', (e) => {
            e.preventDefault();
            form.querySelectorAll('select').forEach((s) => s.value = '');
            if (champRecherche) champRecherche.value = '';
            chargerFiltres();
        });
    }

    // === Chargement AJAX des plats filtrés ====================== //

    async function chargerFiltres() {
        const params = new URLSearchParams();
        const recherche = champRecherche ? champRecherche.value.trim() : '';
        const categorie = form.querySelector('#categorie').value;
        const regime    = form.querySelector('#regime').value;
        const prix      = form.querySelector('#prix').value;

        if (recherche) params.append('recherche', recherche);
        if (categorie) params.append('categorie', categorie);
        if (regime)    params.append('regime',    regime);
        if (prix)      params.append('prix',      prix);

        loader.style.display = 'block';
        grille.style.opacity = '0.4';

        try {
            const res = await fetch('api/filtrer_plats.php?' + params.toString());
            if (!res.ok) {
                console.error('La requete n\'a pas abouti ' + res.status + ' ' + res.statusText);
                return;
            }
            const data = await res.json();
            if (data.succes) {
                platsCourants = data.plats;
                appliquerTri();   // applique le tri actuel sur les nouvelles données
                majBoutonReset();
            }
        } catch (e) {
            console.error('Erreur avec fetch', e);
        } finally {
            loader.style.display = 'none';
            grille.style.opacity = '1';
        }
    }

    // === Tri côté client (sans nouvel appel AJAX) =============== //

    function appliquerTri() {
        const mode = champTri ? champTri.value : '';
        const plats = [...platsCourants];

        if (mode === 'prix-asc')      plats.sort((a, b) => a.prix - b.prix);
        else if (mode === 'prix-desc') plats.sort((a, b) => b.prix - a.prix);
        else if (mode === 'nom-asc')   plats.sort((a, b) => a.nom.localeCompare(b.nom, 'fr'));
        else if (mode === 'nom-desc')  plats.sort((a, b) => b.nom.localeCompare(a.nom, 'fr'));

        afficherPlats(plats);
    }

    // === Rendu HTML de la grille ================================ //

    function afficherPlats(plats) {
        grille.innerHTML = '';

        if (plats.length === 0) {
            grille.style.display = 'none';
            messageAucun.style.display = 'block';
            return;
        }

        messageAucun.style.display = 'none';
        grille.style.display = '';

        plats.forEach((p) => grille.appendChild(creerCartePlat(p)));
    }

    /**
     * Construit le DOM d'une carte de plat à partir des données JSON.
     * Reproduit fidèlement la structure HTML générée par PHP.
     */
    function creerCartePlat(p) {
        const card = document.createElement('div');
        card.className = 'product-card';
        card.dataset.category = p.categorie;

        const image = document.createElement('div');
        image.className = 'product-image';
        image.innerHTML = '<img src="' + echapper(p.image) + '" alt="' + echapper(p.nom) + '">';

        const info = document.createElement('div');
        info.className = 'product-info';

        info.innerHTML = ''
            + '<h3>' + echapper(p.nom) + '</h3>'
            + '<p class="product-description">' + echapper(p.description) + '</p>'
            + (p.tags && p.tags.length
                ? '<div class="product-tags">'
                  + p.tags.map((t) => '<span class="tag ' + echapper(t) + '">' + echapper(t) + '</span>').join('')
                  + '</div>'
                : '')
            + '<div class="product-footer">'
            + '<span class="price">' + p.prix.toFixed(2).replace('.', ',') + ' €</span>'
            + boutonAjoutHtml(p)
            + '</div>';

        card.appendChild(image);
        card.appendChild(info);
        return card;
    }

    /**
     * Génère le bouton "+ Ajouter" pour un client connecté, ou un
     * lien vers la connexion. On lit la présence du bouton dans
     * le HTML d'origine pour décider.
     */
    function boutonAjoutHtml(p) {
        const exemple = document.querySelector('.product-card .btn-add');
        if (!exemple) return '';

        // Si c'est un <a>, l'utilisateur n'est pas connecté
        if (exemple.tagName === 'A') {
            return '<a href="connexion.php" class="btn-add">Se connecter</a>';
        }

        return '<form method="POST" action="presentation.php" style="display:inline;">'
             + '<input type="hidden" name="plat_id" value="' + p.id + '">'
             + '<button type="submit" class="btn-add">+ Ajouter</button>'
             + '</form>';
    }

    // === Utilitaires =========================================== //

    function echapper(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function majBoutonReset() {
        if (!btnReset) return;
        const aFiltre = (champRecherche && champRecherche.value)
                     || form.querySelector('#categorie').value
                     || form.querySelector('#regime').value
                     || form.querySelector('#prix').value;
        btnReset.style.display = aFiltre ? '' : 'none';
    }

    /**
     * Au premier chargement, on extrait les données des plats déjà
     * rendus par PHP. Évite un appel AJAX initial inutile.
     */
    function lirePlatsDepuisDom() {
        const cartes = grille.querySelectorAll('.product-card');
        const liste = [];
        cartes.forEach((c, i) => {
            const nom         = c.querySelector('h3')?.textContent.trim() || '';
            const description = c.querySelector('.product-description')?.textContent.trim() || '';
            const prixTexte   = c.querySelector('.price')?.textContent.trim().replace(',', '.').replace(/[^\d.]/g, '') || '0';
            const tags        = [...c.querySelectorAll('.product-tags .tag')].map((t) => t.classList[1] || '');
            const image       = c.querySelector('img')?.getAttribute('src') || '';
            const platId      = c.querySelector('input[name="plat_id"]')?.value || (i + 1);

            liste.push({
                id: parseInt(platId, 10),
                nom,
                description,
                prix: parseFloat(prixTexte),
                categorie: c.dataset.category || '',
                tags,
                image,
                disponible: true,
            });
        });
        return liste;
    }
});
