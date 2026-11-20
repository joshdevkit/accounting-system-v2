<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();

        if (! $company) {
            $this->command?->warn('No company found. Contact seeder skipped.');

            return;
        }

        $contacts = [
            [
                'name' => 'AURA SPA',
                'address' => 'QUEZON ST., DISTRICT I, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '463-781-682-00000',
            ],
            [
                'name' => 'B & B BAKES AND FOOD SERVICES',
                'address' => 'ALBANO STREET, DISTRICT III, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '602-676-571-00000',
            ],
            [
                'name' => 'BE OFFICE AND SCHOOL SUPPLIES TRADING',
                'address' => 'RIZAL AVENUE, DISTRICT II 3305, CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '122-678-406-00000',
            ],
            [
                'name' => 'BRICOLAGE PHILIPPINES INC',
                'address' => 'DISTRICT III POB. CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '010-057-617-00324',
            ],
            [
                'name' => 'CHE CHE STORE AND PLASTIC SUPPLY',
                'address' => 'PRIMARK TOWN CENTER SAN FERMIN, 3305 CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '470-397-581-00000',
            ],
            [
                'name' => "CHING'S PSALUBONG AND KAINAN",
                'address' => '#25 PILAR STREET, DISTRICT III, CAUYAN CITY, ISABELA',
                'tax_identification_number' => '335-120-808-00001',
            ],
            [
                'name' => 'CIVIL AVIATION AUTHORITY OF THE PHILIPPINES',
                'address' => 'SAN FERMIN 3305 CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '000-652-861-00047',
            ],
            [
                'name' => 'CM MARZONIA MARBLE & CONCRETE PRODUCTS',
                'address' => 'SAN FERMIN, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '945-081-187-000',
            ],
            [
                'name' => 'COCA-COLA EUROPACIFIC ABOITIZ PHILIPPINES, INC',
                'address' => 'NATIONAL HIGHWAY, DISTRICT 1, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '000112104-089',
            ],
            [
                'name' => "DIAMANTINA'S BAKERY",
                'address' => 'DIAMANTINA 3315, CABATUAN, ISABELA',
                'tax_identification_number' => '478-985-373-00000',
            ],
            [
                'name' => 'DNF DISTRIBUTION AND LOGISTICS, INC',
                'address' => 'MAHARLIKA HIGHWAY CABARUAN 3305 CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '009-856-084-00000',
            ],
            [
                'name' => 'FAMOUS PARTS & AUTO SUPPLY',
                'address' => 'CANCILLER AVE., DISTRICT 1, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '168-087-522-00000',
            ],
            [
                'name' => 'FIESTA MART',
                'address' => 'PRENZA NATIONAL HIGHWAY SAN FERMIN CAUAYAN CITY',
                'tax_identification_number' => '010-125-412-000',
            ],
            [
                'name' => 'GOLD.DRAGON FRUITS AND VEGETABLES TRADING',
                'address' => 'NATIONAL HIGHWAY, DISTRICT 1, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '301-015-902-00000',
            ],
            [
                'name' => 'GRUPO MARILEN, INC',
                'address' => 'GMI COMPOUND, MABINI, CITY OF SANTIAGO, ISABELA',
                'tax_identification_number' => '004-062-427-00000',
            ],
            [
                'name' => 'HAPPY GO LUCKY MART RETAIL OPC',
                'address' => '#99 RIZAL AVENUE DISTRICT III CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '010-858-468-00000',
            ],
            [
                'name' => 'HATAW SHOPPING MALL CORPORATION',
                'address' => 'DISTRICT II CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '645-582-019-00001',
            ],
            [
                'name' => 'IKA TRADING AND CONSTRUCTION CORPORATION',
                'address' => '168 MAHARLIKA HIGHWAY, SAN FERMIN 3305, CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '323-009-059-00000',
            ],
            [
                'name' => 'INANG SUSAN MULTISERVE CORPORATION',
                'address' => 'PUROK 5, LABINAB IV 3305, CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '666-437-365-00000',
            ],
            [
                'name' => 'INTERNATIONAL PHARMACEUTICALS, INC',
                'address' => 'BUGALLON NORTE, RAMON, ISABELA',
                'tax_identification_number' => '000-309-701-00010',
            ],
            [
                'name' => 'ISABELA GIOS COMMERCIAL, INC.',
                'address' => 'NO. 06 BURGOS ST., VICTORY SUR, 3311 SANTIAGO CITY',
                'tax_identification_number' => '008-929-331-00000',
            ],
            [
                'name' => 'ISABELA RC COMMERCIAL',
                'address' => 'CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '102-208-411-00000',
            ],
            [
                'name' => 'JOY-ANNE ENTERPRISES',
                'address' => 'CABATUAN ROAD, SAN FERMIN, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '907-577-519-000',
            ],
            [
                'name' => 'JRS BUSINESS CORPORATION',
                'address' => 'MAHARLIKA HIGHWAY SAN FERMIN CAUAYAN CITY ISABELA',
                'tax_identification_number' => '000-056-694-00083',
            ],
            [
                'name' => "K'S FOOD SERVICES",
                'address' => 'DON JOSE CANCILLER AVE., DISTRICT 1 3305, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '306-552-481-00000',
            ],
            [
                'name' => 'KIMSAN MEINI HOUSEHOLD GOODS WHOLESALING',
                'address' => 'DISTRICT III POB. CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '202-448-910-00002',
            ],
            [
                'name' => 'KINGPIN SUPERSTORE OPC',
                'address' => '105 RIZAL AVE., DIST. III, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => null,
            ],
            [
                'name' => 'KINGPIN SUPERSTORE OPC',
                'address' => 'KING STREET MALL, RIZAL AVE. DISTRICT III, (POB.) 3305 CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '775-787-124-00000',
            ],
            [
                'name' => 'MA. GLORIA NATIVE DELICACIES AND SARI-SARI STORE',
                'address' => 'MAGSAYSAY (POB), NAGUILIAN, ISABELA',
                'tax_identification_number' => '903-457-588-00000',
            ],
            [
                'name' => 'MAKKASTA DISTRIBUTION CORP.',
                'address' => 'NATIONAL HIGHWAY, RANIAG, RAMON, ISABELA',
                'tax_identification_number' => '008-933-440-000',
            ],
            [
                'name' => 'MANILA SOUTHERN ASSOCIATES INC',
                'address' => 'SM CITY CAUAYAN, MAHARLIKA HIGHWAY DISTRICT II (POB) 3305 CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '003-980-505-00134',
            ],
            [
                'name' => 'MARIA DIZON FISH STALL',
                'address' => 'PRIMARK TOWN CENTER, SAN FERMIN, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '928-688-144-000',
            ],
            [
                'name' => 'MILA & JESSIE MEAT DEALER',
                'address' => 'PRIMARK TOWN CENTER, SAN FERMIN, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '268-952-054-000',
            ],
            [
                'name' => 'MVM ENTERPRISES',
                'address' => 'MAGSAYSAY ST., CALAOCAN, CITY OF SANTIAGO, ISABELA',
                'tax_identification_number' => '315-061-490-00000',
            ],
            [
                'name' => 'PHEN WET AND DRY GOODS TRADING',
                'address' => 'DISTRICT II, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '768-801-385-000',
            ],
            [
                'name' => 'PUREGOLD PRICE CLUB, INC',
                'address' => 'RIZAL AVE DISTRICT II POB. CITY OF CAUAYAN ISABAELA',
                'tax_identification_number' => '201-277-095-00093',
            ],
            [
                'name' => 'RAI BUILDERS HOME DEPOT',
                'address' => 'DON JOSE CANCILLER DISTRICT 1, CAUAYAN CITY ISABELA',
                'tax_identification_number' => '178-946-135-00000',
            ],
            [
                'name' => 'ROSE SARI-SARI STORE',
                'address' => 'PUROK 4 TURAYONG, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '680-031-507-000',
            ],
            [
                'name' => 'ROXANNE ENTERPRISES',
                'address' => 'DISTRICT 1 (POB.), CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '907-577-550-00000',
            ],
            [
                'name' => 'SAKTO TY HOUSEHOLD GOODS WHOLESALING',
                'address' => 'DISTRICT 2, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '639-612-228-00001',
            ],
            [
                'name' => 'SAN MIGUEL BREWERY INC',
                'address' => 'PRENZA, NATIONAL HIGHWAY, SAN FERMIN, CAUAYAN CITY',
                'tax_identification_number' => '006-807-251-013',
            ],
            [
                'name' => 'SANFORD MARKETING CORPORATION',
                'address' => 'SAVEMORE MARKET PRIMARK MAHARLIKA HIGHWAY COR CORTES ST SAN FERMIN CAUAYAN ISABELA',
                'tax_identification_number' => '207-961-175-00151',
            ],
            [
                'name' => 'SANITARY CARE PRODUCSTS ASIA, INC',
                'address' => 'NATIONAL HIGHWAY, ALINAM, CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '004-640-600-00018',
            ],
            [
                'name' => 'SHERIDAN MARKETING INC',
                'address' => 'GOLDEN CROWN COMPOUND NATIONAL HIGHWAY DISTRICT 1 CAUAYAN CITY ISABELA',
                'tax_identification_number' => '000-425-144-00015',
            ],
            [
                'name' => 'SM PRIME HOLDINGS, INC',
                'address' => 'SM CITY CAUAYAN, MAHARLIKA HIGHWAY DISTRICT II POB. CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '003-058-789-00082',
            ],
            [
                'name' => 'SNB CORPORATION',
                'address' => '22 RIZAL AVENUE, DISTRICT I, CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '690-871-127-00003',
            ],
            [
                'name' => 'STAR CHIME ENTERPRISES',
                'address' => 'SAN FERMIN, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '123-160-117-00000',
            ],
            [
                'name' => 'STAR HOME GENERAL MERCHANDISE',
                'address' => 'DJCA, DISTRICT 1 (POB.), CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '945-094-174-00000',
            ],
            [
                'name' => 'SUPERVALUE, INC',
                'address' => 'SM SUPERMARKET SM CITY CAUAYAN MAHARLIKA HIGHWAY DISTRICT II (POB.) 3305 CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '000-144-976-00067',
            ],
            [
                'name' => 'SUY SING COMMERCIAL CORPORATION',
                'address' => 'LOT 1-A LOT 1-B & LOT 2-A SAN FERMIN CITY OF CAUAYAN ISABELA',
                'tax_identification_number' => '000-320-806-00020',
            ],
            [
                'name' => 'TALAVERA HYPERMARKET INC',
                'address' => 'QUEZON ST. DISTRICT 1 CAUAYAN CITY ISABELA',
                'tax_identification_number' => '006-599-379-003',
            ],
            [
                'name' => 'UNI-CITY GENERAL MERCHANDISE INC',
                'address' => 'SAN FERMIN CAUAYAN CITY ISABELA',
                'tax_identification_number' => '006-345-486-017',
            ],
            [
                'name' => 'VALUEGEN PHARMACY',
                'address' => 'DISTRICT 3, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '296-292-878-000',
            ],
            [
                'name' => "WALL'S DELICACIES STORE",
                'address' => '216 CANCILLER, DISTRICT I, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '485-478-248-00000',
            ],
            [
                'name' => 'WILCON DEPOT, INC',
                'address' => 'BRGY. TAGARAN, CAUAYAN CITY, ISABELA',
                'tax_identification_number' => '009-192-878-00116',
            ],
            [
                'name' => 'WUZHAN OFFICE SUPPLIES TRADING',
                'address' => 'F.N. DY BLVD., SAN FERMIN, CITY OF CAUAYAN, ISABELA',
                'tax_identification_number' => '476-928-231-00000',
            ],
        ];

        foreach ($contacts as $contact) {
            Contact::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $contact['name'],
                ],
                [
                    'address' => $contact['address'] ?? null,
                    'type' => 'vendor',
                    'tax_identification_number' => $contact['tax_identification_number'] ?? null,
                    'phone' => $contact['phone'] ?? null,
                    'email' => $contact['email'] ?? null,
                    'default_payment_terms_days' => $contact['default_payment_terms_days'] ?? 30,
                    'notes' => $contact['notes'] ?? null,
                    'is_active' => true,
                    'created_by' => $contact['created_by'] ?? null,
                ],
            );
        }

        $this->command?->info(
            count($contacts) . ' vendor contacts seeded successfully.'
        );
    }
}
