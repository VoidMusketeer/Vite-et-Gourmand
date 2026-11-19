<?php
namespace App\Catalog;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

final class MenuPublisher
{
    public function __construct(private Connection $db, private SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public')] private string $publicDir) {}

    public function save(array $data, ?UploadedFile $photo, string $author, ?string $id): void
    {
        foreach (['title','description','starter','main_course','dessert','theme','tag'] as $key) {
            if (!is_string($data[$key] ?? null) || trim($data[$key]) === '' || mb_strlen($data[$key]) > ($key === 'description' ? 5000 : 160)) {
                throw new \DomainException('Renseignez le nom, la description, le thème, le régime et les trois plats.');
            }
        }
        $min = filter_var($data['min'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>9999]]);
        $price = $data['price'] ?? '';
        if (!$min || !is_string($price) || !preg_match('/^\d{1,6}(\.\d{1,2})?$/D',$price) || $price <= 0 || $price*$min >= 100000000) {
            throw new \DomainException('Vérifiez le prix et le nombre minimum de personnes.');
        }
        $status = $data['state'] ?? '';
        $stock=filter_var($data['stock'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
        if ($stock===false) throw new \DomainException('Saisissez un stock entier entre 0 et 1 000 000 menus complets pour des groupes.');
        $start = $data['start_date'] ?? '';
        $end = $data['end_date'] ?? '';
        foreach ([$start,$end] as $date) {
            if ($date === '') continue;
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d',$date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new \DomainException('Date de disponibilité invalide.');
        }
        if ($start && $end && $end < $start) throw new \DomainException('La fin doit suivre le début de disponibilité.');
        if (!in_array($status,['draft','published'],true)) throw new \DomainException('État de publication invalide.');
        $path = null;
        if ($photo) {
            $info = $photo->isValid() && $photo->getSize() <= 5*1024*1024 ? @getimagesize($photo->getPathname()) : false;
            $ext = [IMAGETYPE_JPEG=>'jpg',IMAGETYPE_PNG=>'png',IMAGETYPE_WEBP=>'webp'][$info[2] ?? 0] ?? null;
            if (!$ext) throw new \DomainException('Choisissez une photo JPG, PNG ou WebP de 5 Mo maximum.');
            $path = 'images/menus/uploads/'.bin2hex(random_bytes(16)).'.'.$ext;
            $photo->move($this->publicDir.'/images/menus/uploads', basename($path));
        }
        try {
            $this->db->transactional(function () use ($id,$author,$path,$data,$price,$min,$status,$start,$end,$stock) {
                $meta = ['season'=>'Toute l’année','start'=>null,'end'=>null,'service'=>'Retrait / livraison','allergens'=>[],'options'=>[]];
                if ($id) {
                    $existing = $this->db->fetchAssociative('SELECT catalogue,available_stock FROM menus WHERE id=? FOR UPDATE',[$id]);
                    if (!$existing) throw new \DomainException('Menu introuvable.');
                    $original=filter_var($data['original_stock'] ?? null,FILTER_VALIDATE_INT);
                    if ($original===false) throw new \DomainException('Rechargez le formulaire avant de modifier le stock.');
                    if ($stock===$original) $stock=(int)$existing['available_stock'];
                    elseif ($original!==(int)$existing['available_stock']) throw new \DomainException('Le stock a changé pendant la modification. Rechargez le menu.');
                    $meta = (json_decode($existing['catalogue'] ?? '{}',true) ?: []) + $meta;
                }
                $meta['theme'] = trim($data['theme']);
                $meta['tag'] = trim($data['tag']);
                if ($start || $end) {
                    $meta['start'] = $meta['end'] = null;
                    $meta['season'] = trim(($start ? 'Du '.(new \DateTimeImmutable($start))->format('d/m/Y') : '').($end ? ' au '.(new \DateTimeImmutable($end))->format('d/m/Y') : ''));
                } elseif ($id && $this->db->fetchOne('SELECT count(*) FROM menus WHERE id=? AND (available_from IS NOT NULL OR available_until IS NOT NULL)',[$id])) {
                    $meta['season'] = 'Toute l’année';
                }
                $fields = ['title'=>trim($data['title']),'description'=>trim($data['description']),'minimum_people'=>$min,
                    'minimum_price'=>round($price*$min,2),'publication_status'=>$status,'updated_by'=>$author,'catalogue'=>json_encode($meta,JSON_THROW_ON_ERROR)];
                $fields['available_from'] = $start ?: null;
                $fields['available_stock'] = $stock;
                $fields['available_until'] = $end ? $end.' 23:59:59' : null;
                if ($id) {
                    $this->db->update('menus',$fields,['id'=>$id]);
                    $this->db->executeStatement('DELETE FROM menu_dishes WHERE menu_id=? AND NOT is_optional',[$id]);
                } else {
                    if (!$path) throw new \DomainException('Ajoutez une photo principale.');
                    $slug = strtolower((string)$this->slugger->slug($fields['title'])).'-'.bin2hex(random_bytes(5));
                    $this->db->insert('menus',$fields+['slug'=>$slug,'conditions'=>trim($data['conditions'] ?? ''),'created_by'=>$author]);
                    $id = $this->db->fetchOne('SELECT id FROM menus WHERE slug=?',[$slug]);
                }
                if (isset($data['conditions'])) $this->db->update('menus',['conditions'=>trim($data['conditions'])],['id'=>$id]);
                foreach (['starter','main_course','dessert'] as $index=>$type) {
                    $this->db->executeStatement('WITH dish AS (INSERT INTO dishes(name,dish_type_id) VALUES (?,(SELECT id FROM dish_types WHERE code=?)) RETURNING id)
                        INSERT INTO menu_dishes(menu_id,dish_id,display_order) SELECT ?,id,? FROM dish', [trim($data[$type]),$type,$id,$index]);
                }
                if ($path) {
                    $this->db->executeStatement('UPDATE menu_images SET is_cover=false WHERE menu_id=? AND is_cover',[$id]);
                    $this->db->executeStatement('INSERT INTO menu_images(menu_id,url,alt_text,is_cover) VALUES (?,?,?,true)',[$id,$path,$fields['title']]);
                }
            });
        } catch (\Throwable $e) {
            if ($path && is_file($this->publicDir.'/'.$path)) unlink($this->publicDir.'/'.$path);
            throw $e;
        }
    }
}
