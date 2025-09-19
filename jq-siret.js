$(document).ready(function () {
    $('#siret').on('input', function () {
        let siret = $(this).val().replace(/\D/g, '').slice(0, 14);
        $(this).val(siret);

        if (siret.length === 14) {
            $('#resultat-siret').text('Chargement...');
            $.getJSON('get-siret?siret=' + siret, function (data) {
                if (data.etablissement && data.etablissement.unite_legale) {
                    const etab = data.etablissement;
                    const legal = etab.unite_legale;
                    // Remplir le formulaire caché
                    $('#form_siret').val(siret);
                    $('#siren').val(etab.siren || '');
                    $('#denomination').val(legal.denomination || '');
                    $('#activite').val(legal.activite_principale || '');
                    $('#adresse').val(`${[etab.numero_voie, etab.libelle_voie].filter(Boolean).join(' ')}, ${etab.code_postal} ${etab.libelle_commune}`);
                    $('#code_postal').val(etab.code_postal || '');
                    $('#commune').val(etab.libelle_commune || '');
                    $('#date_creation').val(etab.date_creation || '');
                    $('#etat_administratif').val(legal.etat_administratif || '');
                    $('#forme_juridique').val(getFormeJuridique(legal.categorie_juridique) || '');
                    $('#formEntreprise').show();
                    $('#resultat-siret').hide('Chargement...');
                } else {
                    $('#resultat-siret').html('<span style="color:red;">Données non disponibles pour ce SIRET.</span>');
                    $('#formEntreprise').hide();
                }
            }).fail(function () {
                $('#resultat-siret').html('<span style="color:red;">Erreur lors de la récupération des données.</span>');
                $('#formEntreprise').hide();
            });
        } else {
            $('#resultat-siret').empty();
            $('#formEntreprise').hide();
        }
    });
});
function getSpecialisationFromAPE(codeAPE) {
    const mapAPEtoSpecialisation = {
        '4520A': 'mecanique',
        '4520B': 'mecanique',
        '4520C': 'nettoyage',
        '4520D': 'peintre',
        '4520E': 'carrosserie',
        '3314Z': 'electro',
        '2562B': 'soudeur',
        '7112B': 'controle',
        '4520Z': 'garage' // code générique pour garage si utilisé
    };

    return mapAPEtoSpecialisation[codeAPE] || null;
}
function getFormeJuridique(code) {
    const mapCodes = {
        '5720': 'SARL',
        '5710': 'SAS',
        '9220': 'Association',
        '1000': 'Entreprise individuelle',
        '6530': 'Société civile',
        '5499': 'Entreprise unipersonnelle à responsabilité limitée',
        '9999': 'Non précisé'
        // Ajoute d'autres codes si nécessaire
    };
    return mapCodes[code] || `${code}`;
}