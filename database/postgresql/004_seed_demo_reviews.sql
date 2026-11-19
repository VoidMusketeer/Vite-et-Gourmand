-- Fictional demonstration reviews, not authentic customer testimonials.
-- Only existing completed orders are eligible; existing reviews are preserved.
WITH samples(reference, rating, comment, days_after) AS (
    VALUES
    ('VG-861302', 4, 'Première commande chez vous et bonne surprise. Les portions étaient généreuses, tout était bien emballé. Il manquait juste des indications plus précises pour réchauffer, on a un peu fait au feeling.', 2),
    ('VG-861202', 5, 'Tout le monde a bien mangé, même ceux qui sont habituellement difficiles ! Ça fait plaisir de pouvoir profiter du repas sans passer la matinée en cuisine. Merci à l’équipe.', 1),
    ('VG-861401', 3, 'C’était bon dans l’ensemble, mais la livraison est arrivée avec une vingtaine de minutes de retard et nous avons dû décaler le repas. Les quantités étaient correctes. Un petit appel pour prévenir aurait été apprécié.', 3),
    ('VG-861301', 5, 'Deuxième commande et toujours aussi satisfait. Présentation soignée, produits frais et portions suffisantes, il nous en restait même pour le lendemain. Je recommande.', 2),
    ('VG-861201', 4, 'Très bon repas, les invités ont surtout apprécié le dessert. J’ai trouvé l’entrée un peu petite par rapport au plat, mais personne n’est reparti avec faim. Contact agréable pour organiser la livraison.', 1),
    ('VG-861400', 5, 'Cette fois tout était à l’heure, merci ! Les plats ont beaucoup plu et la remise en température s’est faite sans problème. Une soirée tranquille pour moi et des invités contents.', 2),
    ('VG-861300', 2, 'Un peu déçu cette fois-ci. Le plat était trop salé à notre goût et moins réussi que lors de nos précédentes commandes. La livraison s’est bien passée, mais pour le prix nous attendions mieux.', 1),
    ('VG-861200', 5, 'Encore une bonne expérience. Commande simple, livraison dans le créneau prévu et rien à redire sur les quantités. Le plus appréciable : ne pas avoir à courir partout le jour du repas.', 3),
    ('VG-861100', 4, 'Bon rapport qualité-prix pour un repas à plusieurs. Tout est arrivé proprement conditionné et les plats étaient bons. Seul petit bémol, le dessert était un peu trop sucré pour moi, mais les autres ont aimé.', 2),
    ('VG-861000', 5, 'On s’est régalés ! J’avais peur de manquer en commandant pour le nombre exact de personnes, finalement les portions étaient largement suffisantes. Très contente de cette première commande.', 1)
)
INSERT INTO reviews (order_id, rating, comment, moderation_status, moderated_at, created_at)
SELECT o.id, samples.rating, samples.comment, 'approved',
       ((o.service_date + samples.days_after + 1) + TIME '10:00') AT TIME ZONE 'Europe/Paris',
       ((o.service_date + samples.days_after) + TIME '18:30') AT TIME ZONE 'Europe/Paris'
FROM samples
JOIN orders o ON o.reference = samples.reference
JOIN users u ON u.id = o.user_id
JOIN order_statuses s ON s.id = o.current_status_id
WHERE s.code = 'completed'
  AND o.service_date + samples.days_after + 1 <= CURRENT_DATE
ON CONFLICT (order_id) DO NOTHING
RETURNING id, rating;
