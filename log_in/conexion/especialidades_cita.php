<?php
/**
 * Catálogo de especialidades para agendamiento de citas.
 */
function obtenerCategoriasEspecialidades(): array
{
    return [
        'basicas' => [
            'titulo' => 'Especialidades básicas',
            'icon'   => 'bi-heart-pulse',
            'items'  => [
                'Medicina General', 'Pediatría', 'Ginecología y Obstetricia', 'Medicina Interna',
                'Odontología', 'Psicología', 'Enfermería', 'Nutrición y Dietética',
            ],
        ],
        'medicas' => [
            'titulo' => 'Especialidades médicas',
            'icon'   => 'bi-clipboard2-pulse',
            'items'  => [
                'Cardiología', 'Dermatología', 'Endocrinología', 'Gastroenterología', 'Neumología',
                'Neurología', 'Nefrología', 'Reumatología', 'Infectología', 'Hematología',
                'Oncología', 'Geriatría',
            ],
        ],
        'quirurgicas' => [
            'titulo' => 'Especialidades quirúrgicas',
            'icon'   => 'bi-scissors',
            'items'  => [
                'Cirugía General', 'Ortopedia y Traumatología', 'Urología', 'Neurocirugía',
                'Cirugía Plástica', 'Cirugía Vascular', 'Cirugía Pediátrica', 'Cirugía Maxilofacial',
            ],
        ],
        'materna' => [
            'titulo' => 'Salud femenina y materna',
            'icon'   => 'bi-gender-female',
            'items'  => [
                'Ginecología', 'Obstetricia', 'Planificación Familiar', 'Control Prenatal',
            ],
        ],
        'pediatricas' => [
            'titulo' => 'Especialidades pediátricas',
            'icon'   => 'bi-emoji-smile',
            'items'  => [
                'Pediatría General', 'Neuropediatría', 'Cardiología Pediátrica',
            ],
        ],
        'diagnostico' => [
            'titulo' => 'Apoyo diagnóstico',
            'icon'   => 'bi-camera',
            'items'  => [
                'Laboratorio Clínico', 'Imagenología', 'Radiología', 'Ecografía', 'Mamografía',
                'Electrocardiograma (ECG)', 'Endoscopia',
            ],
        ],
        'mental' => [
            'titulo' => 'Salud mental',
            'icon'   => 'bi-chat-heart',
            'items'  => [
                'Psiquiatría', 'Psicología Clínica', 'Psicoterapia',
            ],
        ],
        'rehabilitacion' => [
            'titulo' => 'Rehabilitación',
            'icon'   => 'bi-person-walking',
            'items'  => [
                'Fisioterapia', 'Terapia Ocupacional', 'Fonoaudiología', 'Rehabilitación Física',
            ],
        ],
        'otras' => [
            'titulo' => 'Otras áreas frecuentes',
            'icon'   => 'bi-eye',
            'items'  => [
                'Oftalmología', 'Optometría', 'Otorrinolaringología',
            ],
        ],
    ];
}

function obtenerTodasEspecialidades(): array
{
    $todas = [];
    foreach (obtenerCategoriasEspecialidades() as $cat) {
        foreach ($cat['items'] as $item) {
            $todas[] = $item;
        }
    }
    return array_values(array_unique($todas));
}

function iconoEspecialidad(string $nombre): string
{
    $map = [
        'Medicina General' => 'bi-heart-pulse', 'Pediatría' => 'bi-emoji-smile', 'Cardiología' => 'bi-activity',
        'Laboratorio Clínico' => 'bi-droplet', 'Imagenología' => 'bi-camera', 'Ginecología' => 'bi-gender-female',
        'Psicología' => 'bi-chat-heart', 'Odontología' => 'bi-bandaid', 'Fisioterapia' => 'bi-person-walking',
        'Oftalmología' => 'bi-eye', 'Psiquiatría' => 'bi-chat-heart',
    ];
    return $map[$nombre] ?? 'bi-hospital';
}
