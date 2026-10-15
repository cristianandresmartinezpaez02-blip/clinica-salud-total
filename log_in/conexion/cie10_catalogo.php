<?php
/**
 * Diagnósticos CIE-10 más frecuentes en consulta externa (selección).
 * El médico puede escribir otro código válido de la CIE-10 con su descripción.
 */
function catalogoCie10(): array
{
    return [
        'A09'   => 'Diarrea y gastroenteritis de presunto origen infeccioso',
        'A08.4' => 'Infección intestinal viral, sin otra especificación',
        'B34.9' => 'Infección viral, no especificada',
        'B35.4' => 'Tiña del cuerpo [tinea corporis]',
        'B37.3' => 'Candidiasis de la vulva y de la vagina',
        'B82.9' => 'Parasitosis intestinal, sin otra especificación',
        'B86'   => 'Escabiosis',
        'D50.9' => 'Anemia por deficiencia de hierro, sin otra especificación',
        'D64.9' => 'Anemia de tipo no especificado',
        'E03.9' => 'Hipotiroidismo, no especificado',
        'E05.9' => 'Tirotoxicosis, no especificada',
        'E10.9' => 'Diabetes mellitus insulinodependiente, sin mención de complicación',
        'E11.9' => 'Diabetes mellitus no insulinodependiente, sin mención de complicación',
        'E28.2' => 'Síndrome de ovario poliquístico',
        'E55.9' => 'Deficiencia de vitamina D, no especificada',
        'E66.9' => 'Obesidad, no especificada',
        'E78.0' => 'Hipercolesterolemia pura',
        'E78.5' => 'Hiperlipidemia, no especificada',
        'F32.9' => 'Episodio depresivo, no especificado',
        'F41.1' => 'Trastorno de ansiedad generalizada',
        'F41.9' => 'Trastorno de ansiedad, no especificado',
        'F51.0' => 'Insomnio no orgánico',
        'F84.0' => 'Autismo en la niñez',
        'F90.0' => 'Perturbación de la actividad y de la atención',
        'G40.9' => 'Epilepsia, tipo no especificado',
        'G43.9' => 'Migraña, no especificada',
        'G44.2' => 'Cefalea debida a tensión',
        'G47.0' => 'Trastornos del inicio y del mantenimiento del sueño [insomnios]',
        'G56.0' => 'Síndrome del túnel carpiano',
        'H10.9' => 'Conjuntivitis, no especificada',
        'H25.9' => 'Catarata senil, no especificada',
        'H40.9' => 'Glaucoma, no especificado',
        'H52.1' => 'Miopía',
        'H60.9' => 'Otitis externa, sin otra especificación',
        'H61.2' => 'Cerumen impactado',
        'H66.9' => 'Otitis media, no especificada',
        'I10'   => 'Hipertensión esencial (primaria)',
        'I25.9' => 'Enfermedad isquémica crónica del corazón, no especificada',
        'I48'   => 'Fibrilación y aleteo auricular',
        'I50.9' => 'Insuficiencia cardíaca, no especificada',
        'I83.9' => 'Venas varicosas de los miembros inferiores sin úlcera ni inflamación',
        'J00'   => 'Rinofaringitis aguda [resfriado común]',
        'J01.9' => 'Sinusitis aguda, no especificada',
        'J02.9' => 'Faringitis aguda, no especificada',
        'J03.9' => 'Amigdalitis aguda, no especificada',
        'J06.9' => 'Infección aguda de las vías respiratorias superiores, no especificada',
        'J11.1' => 'Influenza con otras manifestaciones respiratorias, virus no identificado',
        'J18.9' => 'Neumonía, no especificada',
        'J20.9' => 'Bronquitis aguda, no especificada',
        'J21.9' => 'Bronquiolitis aguda, no especificada',
        'J30.4' => 'Rinitis alérgica, no especificada',
        'J44.9' => 'Enfermedad pulmonar obstructiva crónica, no especificada',
        'J45.9' => 'Asma, no especificada',
        'K02.9' => 'Caries dental, no especificada',
        'K21.9' => 'Enfermedad del reflujo gastroesofágico sin esofagitis',
        'K29.7' => 'Gastritis, no especificada',
        'K30'   => 'Dispepsia',
        'K52.9' => 'Colitis y gastroenteritis no infecciosas, no especificadas',
        'K58.9' => 'Síndrome del colon irritable sin diarrea',
        'K59.0' => 'Constipación',
        'K80.2' => 'Cálculo de la vesícula biliar sin colecistitis',
        'L20.9' => 'Dermatitis atópica, no especificada',
        'L23.9' => 'Dermatitis alérgica de contacto, de causa no especificada',
        'L30.9' => 'Dermatitis, no especificada',
        'L50.9' => 'Urticaria, no especificada',
        'L70.0' => 'Acné vulgar',
        'M17.9' => 'Gonartrosis, no especificada',
        'M19.9' => 'Artrosis, no especificada',
        'M25.5' => 'Dolor en articulación',
        'M54.2' => 'Cervicalgia',
        'M54.5' => 'Lumbago no especificado',
        'M75.1' => 'Síndrome del manguito rotatorio',
        'M79.1' => 'Mialgia',
        'M81.9' => 'Osteoporosis, no especificada',
        'N30.0' => 'Cistitis aguda',
        'N39.0' => 'Infección de vías urinarias, sitio no especificado',
        'N40'   => 'Hiperplasia de la próstata',
        'N76.0' => 'Vaginitis aguda',
        'N92.0' => 'Menstruación excesiva y frecuente con ciclo regular',
        'N94.6' => 'Dismenorrea, no especificada',
        'N95.1' => 'Estados menopáusicos y climatéricos femeninos',
        'R05'   => 'Tos',
        'R07.4' => 'Dolor en el pecho, no especificado',
        'R10.4' => 'Otros dolores abdominales y los no especificados',
        'R11'   => 'Náusea y vómito',
        'R42'   => 'Mareo y desvanecimiento',
        'R50.9' => 'Fiebre, no especificada',
        'R51'   => 'Cefalea',
        'R53'   => 'Malestar y fatiga',
        'R63.4' => 'Pérdida anormal de peso',
        'R73.9' => 'Hiperglicemia, no especificada',
        'S13.4' => 'Esguince y torcedura de la columna cervical',
        'S93.4' => 'Esguince y torcedura del tobillo',
        'T14.0' => 'Traumatismo superficial de región no especificada del cuerpo',
        'T78.4' => 'Alergia no especificada',
        'Z00.0' => 'Examen médico general',
        'Z00.1' => 'Control de salud de rutina del niño',
        'Z01.2' => 'Examen odontológico',
        'Z02.7' => 'Expedición de certificado médico',
        'Z30.0' => 'Consejo y asesoramiento general sobre la anticoncepción',
        'Z34.9' => 'Supervisión de embarazo normal no especificado',
        'Z71.3' => 'Consulta para instrucción y vigilancia de la dieta',
        'Z76.0' => 'Consulta para repetición de receta',
    ];
}

/**
 * Interpreta lo escrito en el campo de diagnóstico: "J00", "j00 — Rinofaringitis…" o un código
 * fuera del catálogo con su descripción ("K35.8 Apendicitis aguda").
 * Devuelve ['codigo' => …, 'descripcion' => …] o null si no es válido.
 */
function interpretarCie10(string $entrada, string $descripcionLibre = ''): ?array
{
    $entrada = trim($entrada);
    if (!preg_match('/^([A-Za-z]\d{2}(?:\.\d{1,2})?)\s*(?:[—–\-:]\s*)?(.*)$/u', $entrada, $m)) {
        return null;
    }
    $codigo = strtoupper($m[1]);
    $catalogo = catalogoCie10();
    if (isset($catalogo[$codigo])) {
        return ['codigo' => $codigo, 'descripcion' => $catalogo[$codigo]];
    }
    $desc = trim($m[2]) !== '' ? trim($m[2]) : trim($descripcionLibre);
    if (mb_strlen($desc) < 4) {
        return null;
    }
    return ['codigo' => $codigo, 'descripcion' => mb_substr($desc, 0, 255)];
}
