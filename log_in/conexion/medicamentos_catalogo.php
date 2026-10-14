<?php
/**
 * Catálogo de medicamentos con precios sugeridos (COP).
 */
function obtenerCatalogoMedicamentos(): array
{
    $lista = [
        'Paracetamol', 'Ibuprofeno', 'Naproxeno', 'Diclofenaco', 'Ketorolaco', 'Tramadol', 'Morfina', 'Codeína', 'Fentanilo', 'Buprenorfina',
        'Metamizol', 'Celecoxib', 'Meloxicam', 'Etoricoxib', 'Piroxicam',
        'Amoxicilina', 'Ampicilina', 'Oxacilina', 'Cefalexina', 'Cefuroxima', 'Ceftriaxona', 'Cefepime', 'Ceftazidima',
        'Ciprofloxacino', 'Levofloxacino', 'Moxifloxacino', 'Azitromicina', 'Claritromicina', 'Eritromicina', 'Clindamicina', 'Doxiciclina',
        'Gentamicina', 'Amikacina', 'Vancomicina', 'Linezolid', 'Meropenem', 'Imipenem', 'Piperacilina/Tazobactam',
        'Metronidazol', 'Nitrofurantoína', 'Fosfomicina',
        'Omeprazol', 'Pantoprazol', 'Esomeprazol', 'Lansoprazol', 'Rabeprazol', 'Sucralfato', 'Hidróxido de Aluminio',
        'Lactulosa', 'Bisacodilo', 'Loperamida', 'Ondansetrón', 'Metoclopramida', 'Dimenhidrinato',
        'Loratadina', 'Cetirizina', 'Clorfeniramina', 'Desloratadina', 'Fexofenadina',
        'Salbutamol', 'Ipratropio', 'Tiotropio', 'Budesonida', 'Beclometasona', 'Fluticasona', 'Formoterol', 'Salmeterol', 'Montelukast',
        'Prednisona', 'Prednisolona', 'Dexametasona', 'Hidrocortisona', 'Metilprednisolona', 'Betametasona',
        'Metformina', 'Glibenclamida', 'Gliclazida', 'Sitagliptina', 'Vildagliptina', 'Empagliflozina',
        'Insulina NPH', 'Insulina Regular', 'Insulina Glargina', 'Insulina Lispro', 'Insulina Aspart',
        'Losartán', 'Enalapril', 'Captopril', 'Valsartán', 'Irbesartán', 'Telmisartán',
        'Amlodipino', 'Nifedipino', 'Verapamilo', 'Diltiazem', 'Atenolol', 'Metoprolol', 'Bisoprolol', 'Carvedilol',
        'Hidroclorotiazida', 'Furosemida', 'Espironolactona', 'Digoxina', 'Amiodarona',
        'Nitroglicerina', 'Ácido Acetilsalicílico', 'Clopidogrel', 'Heparina', 'Enoxaparina', 'Warfarina', 'Rivaroxabán', 'Apixabán', 'Dabigatrán',
        'Atorvastatina', 'Simvastatina', 'Rosuvastatina', 'Fenofibrato',
        'Diazepam', 'Lorazepam', 'Midazolam', 'Alprazolam', 'Clonazepam',
        'Fluoxetina', 'Sertralina', 'Escitalopram', 'Citalopram', 'Paroxetina', 'Venlafaxina', 'Duloxetina', 'Amitriptilina',
        'Haloperidol', 'Risperidona', 'Olanzapina', 'Quetiapina', 'Aripiprazol', 'Clozapina', 'Ziprasidona',
        'Fenitoína', 'Carbamazepina', 'Ácido Valproico', 'Lamotrigina', 'Levetiracetam', 'Topiramato', 'Gabapentina', 'Pregabalina',
        'Aciclovir', 'Oseltamivir', 'Tenofovir', 'Lamivudina', 'Dolutegravir', 'Efavirenz', 'Entecavir',
        'Fluconazol', 'Clotrimazol', 'Nistatina', 'Caspofungina',
        'Albendazol', 'Mebendazol', 'Ivermectina', 'Permetrina',
        'Rifampicina', 'Isoniazida', 'Pirazinamida', 'Etambutol',
        'Sulfato Ferroso', 'Ácido Fólico', 'Vitamina B12', 'Vitamina D', 'Calcio Carbonato', 'Zinc', 'Sulfato de Magnesio',
        'Levotiroxina', 'Metimazol', 'Propiltiouracilo', 'Oxitocina', 'Misoprostol',
        'Lidocaína', 'Bupivacaína', 'Propofol', 'Ketamina', 'Atropina', 'Adrenalina', 'Noradrenalina', 'Dopamina', 'Dobutamina',
        'Acetilcisteína', 'Ambroxol', 'Bromhexina', 'Colchicina', 'Alopurinol',
        'Tamsulosina', 'Finasterida', 'Sildenafil', 'Tadalafilo',
        'Metotrexato', 'Azatioprina', 'Ciclosporina', 'Tacrolimus', 'Hidroxicloroquina', 'Sulfasalazina',
    ];

    $precios = [
        'Paracetamol' => 1800, 'Ibuprofeno' => 2500, 'Naproxeno' => 3200, 'Diclofenaco' => 2800, 'Ketorolaco' => 4500,
        'Tramadol' => 12000, 'Morfina' => 35000, 'Codeína' => 15000, 'Fentanilo' => 85000, 'Buprenorfina' => 42000,
        'Metamizol' => 2200, 'Celecoxib' => 8500, 'Meloxicam' => 5500, 'Etoricoxib' => 9800, 'Piroxicam' => 4800,
        'Amoxicilina' => 6500, 'Ampicilina' => 7200, 'Oxacilina' => 11000, 'Cefalexina' => 8900, 'Cefuroxima' => 14500,
        'Ceftriaxona' => 28000, 'Cefepime' => 52000, 'Ceftazidima' => 48000, 'Ciprofloxacino' => 7500, 'Levofloxacino' => 9200,
        'Moxifloxacino' => 18500, 'Azitromicina' => 12000, 'Claritromicina' => 14000, 'Eritromicina' => 6800, 'Clindamicina' => 11500,
        'Doxiciclina' => 5500, 'Gentamicina' => 8500, 'Amikacina' => 22000, 'Vancomicina' => 65000, 'Linezolid' => 78000,
        'Meropenem' => 95000, 'Imipenem' => 88000, 'Piperacilina/Tazobactam' => 72000, 'Metronidazol' => 4200, 'Nitrofurantoína' => 5800, 'Fosfomicina' => 15000,
        'Omeprazol' => 4500, 'Pantoprazol' => 6200, 'Esomeprazol' => 7800, 'Lansoprazol' => 5500, 'Rabeprazol' => 6800,
        'Sucralfato' => 8500, 'Hidróxido de Aluminio' => 3200, 'Lactulosa' => 12000, 'Bisacodilo' => 4500, 'Loperamida' => 3800,
        'Ondansetrón' => 8500, 'Metoclopramida' => 3500, 'Dimenhidrinato' => 4200,
        'Loratadina' => 4800, 'Cetirizina' => 5200, 'Clorfeniramina' => 2800, 'Desloratadina' => 7500, 'Fexofenadina' => 8200,
        'Salbutamol' => 6500, 'Ipratropio' => 15000, 'Tiotropio' => 45000, 'Budesonida' => 28000, 'Beclometasona' => 22000,
        'Fluticasona' => 35000, 'Formoterol' => 38000, 'Salmeterol' => 42000, 'Montelukast' => 18000,
        'Prednisona' => 4500, 'Prednisolona' => 5200, 'Dexametasona' => 3800, 'Hidrocortisona' => 6500, 'Metilprednisolona' => 12000, 'Betametasona' => 4800,
        'Metformina' => 3500, 'Glibenclamida' => 4200, 'Gliclazida' => 8500, 'Sitagliptina' => 45000, 'Vildagliptina' => 42000, 'Empagliflozina' => 68000,
        'Insulina NPH' => 55000, 'Insulina Regular' => 48000, 'Insulina Glargina' => 120000, 'Insulina Lispro' => 95000, 'Insulina Aspart' => 92000,
        'Losartán' => 6200, 'Enalapril' => 4500, 'Captopril' => 3800, 'Valsartán' => 8500, 'Irbesartán' => 9200, 'Telmisartán' => 7800,
        'Amlodipino' => 5500, 'Nifedipino' => 6200, 'Verapamilo' => 8500, 'Diltiazem' => 7800, 'Atenolol' => 4200, 'Metoprolol' => 5500,
        'Bisoprolol' => 6800, 'Carvedilol' => 9200, 'Hidroclorotiazida' => 3500, 'Furosemida' => 2800, 'Espironolactona' => 5500,
        'Digoxina' => 8500, 'Amiodarona' => 15000, 'Nitroglicerina' => 12000, 'Ácido Acetilsalicílico' => 2500, 'Clopidogrel' => 18000,
        'Heparina' => 25000, 'Enoxaparina' => 45000, 'Warfarina' => 5500, 'Rivaroxabán' => 85000, 'Apixabán' => 92000, 'Dabigatrán' => 78000,
        'Atorvastatina' => 12000, 'Simvastatina' => 8500, 'Rosuvastatina' => 18000, 'Fenofibrato' => 15000,
        'Diazepam' => 4500, 'Lorazepam' => 8500, 'Midazolam' => 12000, 'Alprazolam' => 6500, 'Clonazepam' => 7200,
        'Fluoxetina' => 8500, 'Sertralina' => 9200, 'Escitalopram' => 12000, 'Citalopram' => 7800, 'Paroxetina' => 10500, 'Venlafaxina' => 15000,
        'Duloxetina' => 22000, 'Amitriptilina' => 5500, 'Haloperidol' => 6500, 'Risperidona' => 18000, 'Olanzapina' => 25000,
        'Quetiapina' => 22000, 'Aripiprazol' => 45000, 'Clozapina' => 55000, 'Ziprasidona' => 38000,
        'Fenitoína' => 8500, 'Carbamazepina' => 12000, 'Ácido Valproico' => 9500, 'Lamotrigina' => 18000, 'Levetiracetam' => 22000,
        'Topiramato' => 15000, 'Gabapentina' => 12000, 'Pregabalina' => 28000,
        'Aciclovir' => 8500, 'Oseltamivir' => 45000, 'Tenofovir' => 55000, 'Lamivudina' => 48000, 'Dolutegravir' => 95000, 'Efavirenz' => 65000, 'Entecavir' => 78000,
        'Fluconazol' => 6500, 'Clotrimazol' => 4200, 'Nistatina' => 5500, 'Caspofungina' => 120000,
        'Albendazol' => 3500, 'Mebendazol' => 4200, 'Ivermectina' => 5500, 'Permetrina' => 8500,
        'Rifampicina' => 12000, 'Isoniazida' => 4500, 'Pirazinamida' => 6500, 'Etambutol' => 5500,
        'Sulfato Ferroso' => 3500, 'Ácido Fólico' => 2800, 'Vitamina B12' => 8500, 'Vitamina D' => 12000, 'Calcio Carbonato' => 4500, 'Zinc' => 5500, 'Sulfato de Magnesio' => 4200,
        'Levotiroxina' => 8500, 'Metimazol' => 6500, 'Propiltiouracilo' => 7800, 'Oxitocina' => 25000, 'Misoprostol' => 15000,
        'Lidocaína' => 5500, 'Bupivacaína' => 12000, 'Propofol' => 45000, 'Ketamina' => 35000, 'Atropina' => 8500, 'Adrenalina' => 15000,
        'Noradrenalina' => 28000, 'Dopamina' => 22000, 'Dobutamina' => 35000, 'Acetilcisteína' => 8500, 'Ambroxol' => 4500, 'Bromhexina' => 4200,
        'Colchicina' => 12000, 'Alopurinol' => 5500, 'Tamsulosina' => 15000, 'Finasterida' => 18000, 'Sildenafil' => 22000, 'Tadalafilo' => 28000,
        'Metotrexato' => 25000, 'Azatioprina' => 35000, 'Ciclosporina' => 85000, 'Tacrolimus' => 95000, 'Hidroxicloroquina' => 8500, 'Sulfasalazina' => 12000,
    ];

    $out = [];
    foreach ($lista as $nombre) {
        $out[] = [
            'nombre' => $nombre,
            'descripcion' => 'Medicamento — Clínica Salud Total',
            'precio' => $precios[$nombre] ?? 5000,
        ];
    }
    return $out;
}
