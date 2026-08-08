<?php

/**
 * Barème de l'Impôt sur les Traitements et Salaires (ITS), par tranches
 * mensuelles progressives.
 *
 * IMPORTANT : les taux et seuils ci-dessous sont un GABARIT à titre
 * d'exemple technique uniquement. Ils doivent être vérifiés et ajustés
 * avec un expert-comptable ou la Direction Générale des Impôts avant toute
 * mise en production, car le barème réel dépend du pays, de l'année
 * fiscale et peut évoluer. SDS-RH n'est pas un service de conseil fiscal.
 *
 * Chaque tranche : ['up_to' => plafond mensuel (null = infini), 'rate' => taux]
 * Le calcul est progressif : chaque tranche de revenu est taxée à son
 * propre taux, pas le revenu total au taux de la tranche atteinte.
 */

return [
    'benin' => [
        'brackets' => [
            ['up_to' => 60000, 'rate' => 0.00],
            ['up_to' => 150000, 'rate' => 0.10],
            ['up_to' => 250000, 'rate' => 0.15],
            ['up_to' => 500000, 'rate' => 0.19],
            ['up_to' => null, 'rate' => 0.30],
        ],
    ],
];