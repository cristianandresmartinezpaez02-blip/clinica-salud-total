-- =====================================================================
-- Clínica Salud Total — esquema y datos de DEMOSTRACIÓN
-- No contiene datos personales reales. Usuarios de prueba en README.md
-- =====================================================================
-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 18-06-2026 a las 16:13:11
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `campaez_proyecto`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `agenda_medica`
--

CREATE TABLE `agenda_medica` (
  `id_agenda` int(11) NOT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `disponible` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cita`
--

CREATE TABLE `cita` (
  `id_cita` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `servicio` varchar(100) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `hora` time DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `estado` varchar(30) DEFAULT 'pendiente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) DEFAULT NULL,
  `nombre_medico` varchar(100) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `nombre_clinica` varchar(150) DEFAULT NULL,
  `direccion_clinica` varchar(250) DEFAULT NULL,
  `consultorio` varchar(50) DEFAULT NULL,
  `telefono_clinica` varchar(20) DEFAULT NULL,
  `id_medico_usuario` int(11) DEFAULT NULL,
  `origen` varchar(20) DEFAULT 'web',
  `fecha_llegada` datetime DEFAULT NULL,
  `registrada_por` varchar(120) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consulta`
--

CREATE TABLE `consulta` (
  `id_consulta` int(11) NOT NULL,
  `id_historial` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `fecha_consulta` datetime DEFAULT NULL,
  `motivo` text DEFAULT NULL,
  `diagnostico` text DEFAULT NULL,
  `tratamiento` text DEFAULT NULL,
  `medicamentos_formulados` text DEFAULT NULL,
  `nombre_paciente` varchar(150) DEFAULT NULL,
  `correo_paciente` varchar(100) DEFAULT NULL,
  `telefono_paciente` varchar(20) DEFAULT NULL,
  `id_cita` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contactos`
--

CREATE TABLE `contactos` (
  `id_contacto` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `asunto` varchar(200) NOT NULL,
  `mensaje` text NOT NULL,
  `fecha_contacto` timestamp NOT NULL DEFAULT current_timestamp(),
  `estado` enum('nuevo','en_proceso','resuelto') DEFAULT 'nuevo',
  `respuesta` text DEFAULT NULL,
  `respondido_por` varchar(100) DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_cita`
--

CREATE TABLE `detalle_cita` (
  `id_detalle` int(11) NOT NULL,
  `id_cita` int(11) NOT NULL,
  `nombre_paciente` varchar(200) DEFAULT NULL,
  `numero_referencia` varchar(100) DEFAULT NULL,
  `fecha_cita` date DEFAULT NULL,
  `hora_cita` time DEFAULT NULL,
  `nombre_medico` varchar(200) DEFAULT NULL,
  `especialidad` varchar(200) DEFAULT NULL,
  `nombre_clinica` varchar(200) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `consultorio` varchar(100) DEFAULT NULL,
  `telefono_contacto` varchar(50) DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_factura`
--

CREATE TABLE `detalle_factura` (
  `id_detalle` int(11) NOT NULL,
  `id_factura` int(11) DEFAULT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_receta`
--

CREATE TABLE `detalle_receta` (
  `id_detalle` int(11) NOT NULL,
  `id_receta` int(11) DEFAULT NULL,
  `id_medicamento` int(11) DEFAULT NULL,
  `dosis` varchar(100) DEFAULT NULL,
  `frecuencia` varchar(100) DEFAULT NULL,
  `dias` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `donaciones`
--

CREATE TABLE `donaciones` (
  `id_donacion` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `tipo_donacion` enum('economica','biologica','sangre','organos','medicamentos') NOT NULL DEFAULT 'economica',
  `monto` decimal(10,2) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `fecha_donacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `comentarios` text DEFAULT NULL,
  `estado` enum('pendiente','procesada','completada') DEFAULT 'pendiente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `enfermero`
--

CREATE TABLE `enfermero` (
  `id_enfermero` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `area` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `factura`
--

CREATE TABLE `factura` (
  `id_factura` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `id_consulta` int(11) DEFAULT NULL,
  `id_farmaceuta` int(11) DEFAULT NULL,
  `estado` varchar(30) DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `farmaceuta`
--

CREATE TABLE `farmaceuta` (
  `id_farmaceuta` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `codigo_empleado` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_clinico`
--

CREATE TABLE `historial_clinico` (
  `id_historial` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_apertura` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_medicamentos`
--

CREATE TABLE `inventario_medicamentos` (
  `id_inventario` int(11) NOT NULL,
  `id_medicamento` int(11) DEFAULT NULL,
  `id_proveedor` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `laboratorio`
--

CREATE TABLE `laboratorio` (
  `id_laboratorio` int(11) NOT NULL,
  `id_paciente` int(11) DEFAULT NULL,
  `id_medico` int(11) DEFAULT NULL,
  `examen` varchar(100) DEFAULT NULL,
  `resultado` text DEFAULT NULL,
  `fecha` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicamento`
--

CREATE TABLE `medicamento` (
  `id_medicamento` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `medicamento`
--

INSERT INTO `medicamento` (`id_medicamento`, `nombre`, `descripcion`, `precio`) VALUES
(1, 'Paracetamol', 'Medicamento — Clínica Salud Total', 1800.00),
(2, 'Ibuprofeno', 'Medicamento — Clínica Salud Total', 2500.00),
(3, 'Naproxeno', 'Medicamento — Clínica Salud Total', 3200.00),
(4, 'Diclofenaco', 'Medicamento — Clínica Salud Total', 2800.00),
(5, 'Ketorolaco', 'Medicamento — Clínica Salud Total', 4500.00),
(6, 'Tramadol', 'Medicamento — Clínica Salud Total', 12000.00),
(7, 'Morfina', 'Medicamento — Clínica Salud Total', 35000.00),
(8, 'Codeína', 'Medicamento — Clínica Salud Total', 15000.00),
(9, 'Fentanilo', 'Medicamento — Clínica Salud Total', 85000.00),
(10, 'Buprenorfina', 'Medicamento — Clínica Salud Total', 42000.00),
(11, 'Metamizol', 'Medicamento — Clínica Salud Total', 2200.00),
(12, 'Celecoxib', 'Medicamento — Clínica Salud Total', 8500.00),
(13, 'Meloxicam', 'Medicamento — Clínica Salud Total', 5500.00),
(14, 'Etoricoxib', 'Medicamento — Clínica Salud Total', 9800.00),
(15, 'Piroxicam', 'Medicamento — Clínica Salud Total', 4800.00),
(16, 'Amoxicilina', 'Medicamento — Clínica Salud Total', 6500.00),
(17, 'Ampicilina', 'Medicamento — Clínica Salud Total', 7200.00),
(18, 'Oxacilina', 'Medicamento — Clínica Salud Total', 11000.00),
(19, 'Cefalexina', 'Medicamento — Clínica Salud Total', 8900.00),
(20, 'Cefuroxima', 'Medicamento — Clínica Salud Total', 14500.00),
(21, 'Ceftriaxona', 'Medicamento — Clínica Salud Total', 28000.00),
(22, 'Cefepime', 'Medicamento — Clínica Salud Total', 52000.00),
(23, 'Ceftazidima', 'Medicamento — Clínica Salud Total', 48000.00),
(24, 'Ciprofloxacino', 'Medicamento — Clínica Salud Total', 7500.00),
(25, 'Levofloxacino', 'Medicamento — Clínica Salud Total', 9200.00),
(26, 'Moxifloxacino', 'Medicamento — Clínica Salud Total', 18500.00),
(27, 'Azitromicina', 'Medicamento — Clínica Salud Total', 12000.00),
(28, 'Claritromicina', 'Medicamento — Clínica Salud Total', 14000.00),
(29, 'Eritromicina', 'Medicamento — Clínica Salud Total', 6800.00),
(30, 'Clindamicina', 'Medicamento — Clínica Salud Total', 11500.00),
(31, 'Doxiciclina', 'Medicamento — Clínica Salud Total', 5500.00),
(32, 'Gentamicina', 'Medicamento — Clínica Salud Total', 8500.00),
(33, 'Amikacina', 'Medicamento — Clínica Salud Total', 22000.00),
(34, 'Vancomicina', 'Medicamento — Clínica Salud Total', 65000.00),
(35, 'Linezolid', 'Medicamento — Clínica Salud Total', 78000.00),
(36, 'Meropenem', 'Medicamento — Clínica Salud Total', 95000.00),
(37, 'Imipenem', 'Medicamento — Clínica Salud Total', 88000.00),
(38, 'Piperacilina/Tazobactam', 'Medicamento — Clínica Salud Total', 72000.00),
(39, 'Metronidazol', 'Medicamento — Clínica Salud Total', 4200.00),
(40, 'Nitrofurantoína', 'Medicamento — Clínica Salud Total', 5800.00),
(41, 'Fosfomicina', 'Medicamento — Clínica Salud Total', 15000.00),
(42, 'Omeprazol', 'Medicamento — Clínica Salud Total', 4500.00),
(43, 'Pantoprazol', 'Medicamento — Clínica Salud Total', 6200.00),
(44, 'Esomeprazol', 'Medicamento — Clínica Salud Total', 7800.00),
(45, 'Lansoprazol', 'Medicamento — Clínica Salud Total', 5500.00),
(46, 'Rabeprazol', 'Medicamento — Clínica Salud Total', 6800.00),
(47, 'Sucralfato', 'Medicamento — Clínica Salud Total', 8500.00),
(48, 'Hidróxido de Aluminio', 'Medicamento — Clínica Salud Total', 3200.00),
(49, 'Lactulosa', 'Medicamento — Clínica Salud Total', 12000.00),
(50, 'Bisacodilo', 'Medicamento — Clínica Salud Total', 4500.00),
(51, 'Loperamida', 'Medicamento — Clínica Salud Total', 3800.00),
(52, 'Ondansetrón', 'Medicamento — Clínica Salud Total', 8500.00),
(53, 'Metoclopramida', 'Medicamento — Clínica Salud Total', 3500.00),
(54, 'Dimenhidrinato', 'Medicamento — Clínica Salud Total', 4200.00),
(55, 'Loratadina', 'Medicamento — Clínica Salud Total', 4800.00),
(56, 'Cetirizina', 'Medicamento — Clínica Salud Total', 5200.00),
(57, 'Clorfeniramina', 'Medicamento — Clínica Salud Total', 2800.00),
(58, 'Desloratadina', 'Medicamento — Clínica Salud Total', 7500.00),
(59, 'Fexofenadina', 'Medicamento — Clínica Salud Total', 8200.00),
(60, 'Salbutamol', 'Medicamento — Clínica Salud Total', 6500.00),
(61, 'Ipratropio', 'Medicamento — Clínica Salud Total', 15000.00),
(62, 'Tiotropio', 'Medicamento — Clínica Salud Total', 45000.00),
(63, 'Budesonida', 'Medicamento — Clínica Salud Total', 28000.00),
(64, 'Beclometasona', 'Medicamento — Clínica Salud Total', 22000.00),
(65, 'Fluticasona', 'Medicamento — Clínica Salud Total', 35000.00),
(66, 'Formoterol', 'Medicamento — Clínica Salud Total', 38000.00),
(67, 'Salmeterol', 'Medicamento — Clínica Salud Total', 42000.00),
(68, 'Montelukast', 'Medicamento — Clínica Salud Total', 18000.00),
(69, 'Prednisona', 'Medicamento — Clínica Salud Total', 4500.00),
(70, 'Prednisolona', 'Medicamento — Clínica Salud Total', 5200.00),
(71, 'Dexametasona', 'Medicamento — Clínica Salud Total', 3800.00),
(72, 'Hidrocortisona', 'Medicamento — Clínica Salud Total', 6500.00),
(73, 'Metilprednisolona', 'Medicamento — Clínica Salud Total', 12000.00),
(74, 'Betametasona', 'Medicamento — Clínica Salud Total', 4800.00),
(75, 'Metformina', 'Medicamento — Clínica Salud Total', 3500.00),
(76, 'Glibenclamida', 'Medicamento — Clínica Salud Total', 4200.00),
(77, 'Gliclazida', 'Medicamento — Clínica Salud Total', 8500.00),
(78, 'Sitagliptina', 'Medicamento — Clínica Salud Total', 45000.00),
(79, 'Vildagliptina', 'Medicamento — Clínica Salud Total', 42000.00),
(80, 'Empagliflozina', 'Medicamento — Clínica Salud Total', 68000.00),
(81, 'Insulina NPH', 'Medicamento — Clínica Salud Total', 55000.00),
(82, 'Insulina Regular', 'Medicamento — Clínica Salud Total', 48000.00),
(83, 'Insulina Glargina', 'Medicamento — Clínica Salud Total', 120000.00),
(84, 'Insulina Lispro', 'Medicamento — Clínica Salud Total', 95000.00),
(85, 'Insulina Aspart', 'Medicamento — Clínica Salud Total', 92000.00),
(86, 'Losartán', 'Medicamento — Clínica Salud Total', 6200.00),
(87, 'Enalapril', 'Medicamento — Clínica Salud Total', 4500.00),
(88, 'Captopril', 'Medicamento — Clínica Salud Total', 3800.00),
(89, 'Valsartán', 'Medicamento — Clínica Salud Total', 8500.00),
(90, 'Irbesartán', 'Medicamento — Clínica Salud Total', 9200.00),
(91, 'Telmisartán', 'Medicamento — Clínica Salud Total', 7800.00),
(92, 'Amlodipino', 'Medicamento — Clínica Salud Total', 5500.00),
(93, 'Nifedipino', 'Medicamento — Clínica Salud Total', 6200.00),
(94, 'Verapamilo', 'Medicamento — Clínica Salud Total', 8500.00),
(95, 'Diltiazem', 'Medicamento — Clínica Salud Total', 7800.00),
(96, 'Atenolol', 'Medicamento — Clínica Salud Total', 4200.00),
(97, 'Metoprolol', 'Medicamento — Clínica Salud Total', 5500.00),
(98, 'Bisoprolol', 'Medicamento — Clínica Salud Total', 6800.00),
(99, 'Carvedilol', 'Medicamento — Clínica Salud Total', 9200.00),
(100, 'Hidroclorotiazida', 'Medicamento — Clínica Salud Total', 3500.00),
(101, 'Furosemida', 'Medicamento — Clínica Salud Total', 2800.00),
(102, 'Espironolactona', 'Medicamento — Clínica Salud Total', 5500.00),
(103, 'Digoxina', 'Medicamento — Clínica Salud Total', 8500.00),
(104, 'Amiodarona', 'Medicamento — Clínica Salud Total', 15000.00),
(105, 'Nitroglicerina', 'Medicamento — Clínica Salud Total', 12000.00),
(106, 'Ácido Acetilsalicílico', 'Medicamento — Clínica Salud Total', 2500.00),
(107, 'Clopidogrel', 'Medicamento — Clínica Salud Total', 18000.00),
(108, 'Heparina', 'Medicamento — Clínica Salud Total', 25000.00),
(109, 'Enoxaparina', 'Medicamento — Clínica Salud Total', 45000.00),
(110, 'Warfarina', 'Medicamento — Clínica Salud Total', 5500.00),
(111, 'Rivaroxabán', 'Medicamento — Clínica Salud Total', 85000.00),
(112, 'Apixabán', 'Medicamento — Clínica Salud Total', 92000.00),
(113, 'Dabigatrán', 'Medicamento — Clínica Salud Total', 78000.00),
(114, 'Atorvastatina', 'Medicamento — Clínica Salud Total', 12000.00),
(115, 'Simvastatina', 'Medicamento — Clínica Salud Total', 8500.00),
(116, 'Rosuvastatina', 'Medicamento — Clínica Salud Total', 18000.00),
(117, 'Fenofibrato', 'Medicamento — Clínica Salud Total', 15000.00),
(118, 'Diazepam', 'Medicamento — Clínica Salud Total', 4500.00),
(119, 'Lorazepam', 'Medicamento — Clínica Salud Total', 8500.00),
(120, 'Midazolam', 'Medicamento — Clínica Salud Total', 12000.00),
(121, 'Alprazolam', 'Medicamento — Clínica Salud Total', 6500.00),
(122, 'Clonazepam', 'Medicamento — Clínica Salud Total', 7200.00),
(123, 'Fluoxetina', 'Medicamento — Clínica Salud Total', 8500.00),
(124, 'Sertralina', 'Medicamento — Clínica Salud Total', 9200.00),
(125, 'Escitalopram', 'Medicamento — Clínica Salud Total', 12000.00),
(126, 'Citalopram', 'Medicamento — Clínica Salud Total', 7800.00),
(127, 'Paroxetina', 'Medicamento — Clínica Salud Total', 10500.00),
(128, 'Venlafaxina', 'Medicamento — Clínica Salud Total', 15000.00),
(129, 'Duloxetina', 'Medicamento — Clínica Salud Total', 22000.00),
(130, 'Amitriptilina', 'Medicamento — Clínica Salud Total', 5500.00),
(131, 'Haloperidol', 'Medicamento — Clínica Salud Total', 6500.00),
(132, 'Risperidona', 'Medicamento — Clínica Salud Total', 18000.00),
(133, 'Olanzapina', 'Medicamento — Clínica Salud Total', 25000.00),
(134, 'Quetiapina', 'Medicamento — Clínica Salud Total', 22000.00),
(135, 'Aripiprazol', 'Medicamento — Clínica Salud Total', 45000.00),
(136, 'Clozapina', 'Medicamento — Clínica Salud Total', 55000.00),
(137, 'Ziprasidona', 'Medicamento — Clínica Salud Total', 38000.00),
(138, 'Fenitoína', 'Medicamento — Clínica Salud Total', 8500.00),
(139, 'Carbamazepina', 'Medicamento — Clínica Salud Total', 12000.00),
(140, 'Ácido Valproico', 'Medicamento — Clínica Salud Total', 9500.00),
(141, 'Lamotrigina', 'Medicamento — Clínica Salud Total', 18000.00),
(142, 'Levetiracetam', 'Medicamento — Clínica Salud Total', 22000.00),
(143, 'Topiramato', 'Medicamento — Clínica Salud Total', 15000.00),
(144, 'Gabapentina', 'Medicamento — Clínica Salud Total', 12000.00),
(145, 'Pregabalina', 'Medicamento — Clínica Salud Total', 28000.00),
(146, 'Aciclovir', 'Medicamento — Clínica Salud Total', 8500.00),
(147, 'Oseltamivir', 'Medicamento — Clínica Salud Total', 45000.00),
(148, 'Tenofovir', 'Medicamento — Clínica Salud Total', 55000.00),
(149, 'Lamivudina', 'Medicamento — Clínica Salud Total', 48000.00),
(150, 'Dolutegravir', 'Medicamento — Clínica Salud Total', 95000.00),
(151, 'Efavirenz', 'Medicamento — Clínica Salud Total', 65000.00),
(152, 'Entecavir', 'Medicamento — Clínica Salud Total', 78000.00),
(153, 'Fluconazol', 'Medicamento — Clínica Salud Total', 6500.00),
(154, 'Clotrimazol', 'Medicamento — Clínica Salud Total', 4200.00),
(155, 'Nistatina', 'Medicamento — Clínica Salud Total', 5500.00),
(156, 'Caspofungina', 'Medicamento — Clínica Salud Total', 120000.00),
(157, 'Albendazol', 'Medicamento — Clínica Salud Total', 3500.00),
(158, 'Mebendazol', 'Medicamento — Clínica Salud Total', 4200.00),
(159, 'Ivermectina', 'Medicamento — Clínica Salud Total', 5500.00),
(160, 'Permetrina', 'Medicamento — Clínica Salud Total', 8500.00),
(161, 'Rifampicina', 'Medicamento — Clínica Salud Total', 12000.00),
(162, 'Isoniazida', 'Medicamento — Clínica Salud Total', 4500.00),
(163, 'Pirazinamida', 'Medicamento — Clínica Salud Total', 6500.00),
(164, 'Etambutol', 'Medicamento — Clínica Salud Total', 5500.00),
(165, 'Sulfato Ferroso', 'Medicamento — Clínica Salud Total', 3500.00),
(166, 'Ácido Fólico', 'Medicamento — Clínica Salud Total', 2800.00),
(167, 'Vitamina B12', 'Medicamento — Clínica Salud Total', 8500.00),
(168, 'Vitamina D', 'Medicamento — Clínica Salud Total', 12000.00),
(169, 'Calcio Carbonato', 'Medicamento — Clínica Salud Total', 4500.00),
(170, 'Zinc', 'Medicamento — Clínica Salud Total', 5500.00),
(171, 'Sulfato de Magnesio', 'Medicamento — Clínica Salud Total', 4200.00),
(172, 'Levotiroxina', 'Medicamento — Clínica Salud Total', 8500.00),
(173, 'Metimazol', 'Medicamento — Clínica Salud Total', 6500.00),
(174, 'Propiltiouracilo', 'Medicamento — Clínica Salud Total', 7800.00),
(175, 'Oxitocina', 'Medicamento — Clínica Salud Total', 25000.00),
(176, 'Misoprostol', 'Medicamento — Clínica Salud Total', 15000.00),
(177, 'Lidocaína', 'Medicamento — Clínica Salud Total', 5500.00),
(178, 'Bupivacaína', 'Medicamento — Clínica Salud Total', 12000.00),
(179, 'Propofol', 'Medicamento — Clínica Salud Total', 45000.00),
(180, 'Ketamina', 'Medicamento — Clínica Salud Total', 35000.00),
(181, 'Atropina', 'Medicamento — Clínica Salud Total', 8500.00),
(182, 'Adrenalina', 'Medicamento — Clínica Salud Total', 15000.00),
(183, 'Noradrenalina', 'Medicamento — Clínica Salud Total', 28000.00),
(184, 'Dopamina', 'Medicamento — Clínica Salud Total', 22000.00),
(185, 'Dobutamina', 'Medicamento — Clínica Salud Total', 35000.00),
(186, 'Acetilcisteína', 'Medicamento — Clínica Salud Total', 8500.00),
(187, 'Ambroxol', 'Medicamento — Clínica Salud Total', 4500.00),
(188, 'Bromhexina', 'Medicamento — Clínica Salud Total', 4200.00),
(189, 'Colchicina', 'Medicamento — Clínica Salud Total', 12000.00),
(190, 'Alopurinol', 'Medicamento — Clínica Salud Total', 5500.00),
(191, 'Tamsulosina', 'Medicamento — Clínica Salud Total', 15000.00),
(192, 'Finasterida', 'Medicamento — Clínica Salud Total', 18000.00),
(193, 'Sildenafil', 'Medicamento — Clínica Salud Total', 22000.00),
(194, 'Tadalafilo', 'Medicamento — Clínica Salud Total', 28000.00),
(195, 'Metotrexato', 'Medicamento — Clínica Salud Total', 25000.00),
(196, 'Azatioprina', 'Medicamento — Clínica Salud Total', 35000.00),
(197, 'Ciclosporina', 'Medicamento — Clínica Salud Total', 85000.00),
(198, 'Tacrolimus', 'Medicamento — Clínica Salud Total', 95000.00),
(199, 'Hidroxicloroquina', 'Medicamento — Clínica Salud Total', 8500.00),
(200, 'Sulfasalazina', 'Medicamento — Clínica Salud Total', 12000.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medico`
--

CREATE TABLE `medico` (
  `id_medico` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `especialidad` varchar(100) DEFAULT NULL,
  `licencia_medica` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id_notificacion` int(11) NOT NULL,
  `tipo_usuario` varchar(50) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `id_usuario` int(11) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones_paciente`
--

CREATE TABLE `notificaciones_paciente` (
  `id_notificacion` int(11) NOT NULL,
  `id_paciente` int(11) NOT NULL,
  `id_cita` int(11) NOT NULL,
  `titulo` varchar(255) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `leida` enum('si','no') DEFAULT 'no'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paciente`
--

CREATE TABLE `paciente` (
  `id_paciente` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `documento` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `sexo` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id_pago` int(11) NOT NULL,
  `id_factura` int(11) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `valor` decimal(10,2) DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--

CREATE TABLE `proveedor` (
  `id_proveedor` int(11) NOT NULL,
  `nombre_empresa` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `receta`
--

CREATE TABLE `receta` (
  `id_receta` int(11) NOT NULL,
  `id_consulta` int(11) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` enum('admin','recepcion','medico','enfermero','farmaceuta','paciente') NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `documento` varchar(20) DEFAULT NULL,
  `tipo_documento` varchar(10) DEFAULT NULL,
  `verificacion_identidad` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `foto_documento` varchar(255) DEFAULT NULL,
  `foto_selfie_documento` varchar(255) DEFAULT NULL,
  `fecha_verificacion_identidad` datetime DEFAULT NULL,
  `verificado_por` varchar(100) DEFAULT NULL,
  `motivo_rechazo_identidad` varchar(255) DEFAULT NULL,
  `acepto_tratamiento_datos` tinyint(1) DEFAULT 0,
  `fecha_acepto_tratamiento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--
-- Usuarios de DEMOSTRACIÓN (ver README para las contraseñas)
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `usuario`, `correo`, `telefono`, `clave`, `rol`, `foto`, `estado`, `fecha_registro`, `documento`, `tipo_documento`, `verificacion_identidad`, `foto_documento`, `foto_selfie_documento`, `fecha_verificacion_identidad`, `verificado_por`, `motivo_rechazo_identidad`, `acepto_tratamiento_datos`, `fecha_acepto_tratamiento`) VALUES
(1, 'Ana', 'Administradora', 'admin', 'admin@demo.local', '3000000001', '$2y$10$TtJGoCjH8.3RN.9F5DXdSOycoZm0yDIvUr8Y/QOE4AmscuQgnezhy', 'admin', NULL, 'activo', '2026-01-01 08:00:00', '1000000001', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00'),
(2, 'Mario', 'Médico', 'medico', 'medico@demo.local', '3000000002', '$2y$10$jb2XemeyzrDXfOAchTsJfOrFYgehKRw9K.2kMDQmMKa6580QMQv5.', 'medico', NULL, 'activo', '2026-01-01 08:00:00', '1000000002', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00'),
(3, 'Elena', 'Enfermera', 'enfermero', 'enfermero@demo.local', '3000000003', '$2y$10$CPtaOD768l2bRtdEr620cuPGcwsubiceTQQ2aBiaZf.gUZuGaCrNa', 'enfermero', NULL, 'activo', '2026-01-01 08:00:00', '1000000003', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00'),
(4, 'Felipe', 'Farmacia', 'farmacia', 'farmacia@demo.local', '3000000004', '$2y$10$Ph2LJ6PEAIF6TNC.Gx/iqeqAQ4bKtEJVg5.Yg.qeZhN7Mgncs1kym', 'farmaceuta', NULL, 'activo', '2026-01-01 08:00:00', '1000000004', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00'),
(5, 'Paula', 'Paciente', 'paciente', 'paciente@demo.local', '3000000005', '$2y$10$d0saDdQnA6PDrgu4VF3DXOc4AC1K8O.BBGCK/EONaLxctz8FX3Bt.', 'paciente', NULL, 'activo', '2026-01-01 08:00:00', '1000000005', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00'),
(6, 'Rosa', 'Recepción', 'recepcion', 'recepcion@demo.local', '3000000006', '$2y$10$G0j9UwxaDaPLmquA4UoIQOpaRrr/yWXwAWIwux/fYodQb4hRkHvby', 'recepcion', NULL, 'activo', '2026-01-01 08:00:00', '1000000006', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00'),
(7, 'Laura', 'Pediatra', 'pediatra', 'pediatra@demo.local', '3000000007', '$2y$10$752XMdVED1aKLRNRpRzvFekdpl8R/ie.BLMvCiDHaxNOg0y.1B..K', 'medico', NULL, 'activo', '2026-01-01 08:00:00', '1000000007', 'CC', 'aprobada', NULL, NULL, '2026-01-01 08:00:00', 'Sistema', NULL, 1, '2026-01-01 08:00:00');

INSERT INTO `medico` (`id_medico`, `id_usuario`, `especialidad`, `licencia_medica`) VALUES (1, 2, 'Medicina General', 'RM-0001'), (2, 7, 'Pediatría', 'RM-0002');
INSERT INTO `enfermero` (`id_enfermero`, `id_usuario`, `area`) VALUES (1, 3, 'Urgencias');
INSERT INTO `farmaceuta` (`id_farmaceuta`, `id_usuario`, `codigo_empleado`) VALUES (1, 4, 'FAR-0001');
INSERT INTO `paciente` (`id_paciente`, `id_usuario`, `documento`, `fecha_nacimiento`, `sexo`, `direccion`) VALUES (1, 5, '1000000005', '1990-05-15', 'Femenino', 'Calle de ejemplo 123');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `agenda_medica`
--
ALTER TABLE `agenda_medica`
  ADD PRIMARY KEY (`id_agenda`),
  ADD KEY `id_medico` (`id_medico`);

--
-- Indices de la tabla `cita`
--
ALTER TABLE `cita`
  ADD PRIMARY KEY (`id_cita`);

--
-- Indices de la tabla `consulta`
--
ALTER TABLE `consulta`
  ADD PRIMARY KEY (`id_consulta`),
  ADD KEY `id_historial` (`id_historial`),
  ADD KEY `id_medico` (`id_medico`);

--
-- Indices de la tabla `contactos`
--
ALTER TABLE `contactos`
  ADD PRIMARY KEY (`id_contacto`);

--
-- Indices de la tabla `detalle_cita`
--
ALTER TABLE `detalle_cita`
  ADD PRIMARY KEY (`id_detalle`);

--
-- Indices de la tabla `detalle_factura`
--
ALTER TABLE `detalle_factura`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_factura` (`id_factura`);

--
-- Indices de la tabla `detalle_receta`
--
ALTER TABLE `detalle_receta`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_receta` (`id_receta`),
  ADD KEY `id_medicamento` (`id_medicamento`);

--
-- Indices de la tabla `donaciones`
--
ALTER TABLE `donaciones`
  ADD PRIMARY KEY (`id_donacion`);

--
-- Indices de la tabla `enfermero`
--
ALTER TABLE `enfermero`
  ADD PRIMARY KEY (`id_enfermero`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `factura`
--
ALTER TABLE `factura`
  ADD PRIMARY KEY (`id_factura`),
  ADD KEY `id_paciente` (`id_paciente`);

--
-- Indices de la tabla `farmaceuta`
--
ALTER TABLE `farmaceuta`
  ADD PRIMARY KEY (`id_farmaceuta`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_paciente` (`id_paciente`);

--
-- Indices de la tabla `inventario_medicamentos`
--
ALTER TABLE `inventario_medicamentos`
  ADD PRIMARY KEY (`id_inventario`),
  ADD KEY `id_medicamento` (`id_medicamento`),
  ADD KEY `id_proveedor` (`id_proveedor`);

--
-- Indices de la tabla `laboratorio`
--
ALTER TABLE `laboratorio`
  ADD PRIMARY KEY (`id_laboratorio`),
  ADD KEY `id_paciente` (`id_paciente`),
  ADD KEY `id_medico` (`id_medico`);

--
-- Indices de la tabla `medicamento`
--
ALTER TABLE `medicamento`
  ADD PRIMARY KEY (`id_medicamento`);

--
-- Indices de la tabla `medico`
--
ALTER TABLE `medico`
  ADD PRIMARY KEY (`id_medico`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id_notificacion`);

--
-- Indices de la tabla `notificaciones_paciente`
--
ALTER TABLE `notificaciones_paciente`
  ADD PRIMARY KEY (`id_notificacion`);

--
-- Indices de la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD PRIMARY KEY (`id_paciente`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id_pago`),
  ADD KEY `id_factura` (`id_factura`);

--
-- Indices de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`id_proveedor`);

--
-- Indices de la tabla `receta`
--
ALTER TABLE `receta`
  ADD PRIMARY KEY (`id_receta`),
  ADD KEY `id_consulta` (`id_consulta`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `usuario` (`usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `agenda_medica`
--
ALTER TABLE `agenda_medica`
  MODIFY `id_agenda` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cita`
--
ALTER TABLE `cita`
  MODIFY `id_cita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `consulta`
--
ALTER TABLE `consulta`
  MODIFY `id_consulta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `contactos`
--
ALTER TABLE `contactos`
  MODIFY `id_contacto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_cita`
--
ALTER TABLE `detalle_cita`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_factura`
--
ALTER TABLE `detalle_factura`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_receta`
--
ALTER TABLE `detalle_receta`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `donaciones`
--
ALTER TABLE `donaciones`
  MODIFY `id_donacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `enfermero`
--
ALTER TABLE `enfermero`
  MODIFY `id_enfermero` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `factura`
--
ALTER TABLE `factura`
  MODIFY `id_factura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `farmaceuta`
--
ALTER TABLE `farmaceuta`
  MODIFY `id_farmaceuta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `inventario_medicamentos`
--
ALTER TABLE `inventario_medicamentos`
  MODIFY `id_inventario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `laboratorio`
--
ALTER TABLE `laboratorio`
  MODIFY `id_laboratorio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `medicamento`
--
ALTER TABLE `medicamento`
  MODIFY `id_medicamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT de la tabla `medico`
--
ALTER TABLE `medico`
  MODIFY `id_medico` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT de la tabla `notificaciones_paciente`
--
ALTER TABLE `notificaciones_paciente`
  MODIFY `id_notificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `paciente`
--
ALTER TABLE `paciente`
  MODIFY `id_paciente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id_pago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  MODIFY `id_proveedor` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `receta`
--
ALTER TABLE `receta`
  MODIFY `id_receta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `agenda_medica`
--
ALTER TABLE `agenda_medica`
  ADD CONSTRAINT `agenda_medica_ibfk_1` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id_medico`);

--
-- Filtros para la tabla `consulta`
--
ALTER TABLE `consulta`
  ADD CONSTRAINT `consulta_ibfk_1` FOREIGN KEY (`id_historial`) REFERENCES `historial_clinico` (`id_historial`),
  ADD CONSTRAINT `consulta_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id_medico`);

--
-- Filtros para la tabla `detalle_factura`
--
ALTER TABLE `detalle_factura`
  ADD CONSTRAINT `detalle_factura_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`);

--
-- Filtros para la tabla `detalle_receta`
--
ALTER TABLE `detalle_receta`
  ADD CONSTRAINT `detalle_receta_ibfk_1` FOREIGN KEY (`id_receta`) REFERENCES `receta` (`id_receta`),
  ADD CONSTRAINT `detalle_receta_ibfk_2` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamento` (`id_medicamento`);

--
-- Filtros para la tabla `enfermero`
--
ALTER TABLE `enfermero`
  ADD CONSTRAINT `enfermero_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `factura`
--
ALTER TABLE `factura`
  ADD CONSTRAINT `factura_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id_paciente`);

--
-- Filtros para la tabla `farmaceuta`
--
ALTER TABLE `farmaceuta`
  ADD CONSTRAINT `farmaceuta_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `historial_clinico`
--
ALTER TABLE `historial_clinico`
  ADD CONSTRAINT `historial_clinico_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id_paciente`);

--
-- Filtros para la tabla `inventario_medicamentos`
--
ALTER TABLE `inventario_medicamentos`
  ADD CONSTRAINT `inventario_medicamentos_ibfk_1` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamento` (`id_medicamento`),
  ADD CONSTRAINT `inventario_medicamentos_ibfk_2` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`);

--
-- Filtros para la tabla `laboratorio`
--
ALTER TABLE `laboratorio`
  ADD CONSTRAINT `laboratorio_ibfk_1` FOREIGN KEY (`id_paciente`) REFERENCES `paciente` (`id_paciente`),
  ADD CONSTRAINT `laboratorio_ibfk_2` FOREIGN KEY (`id_medico`) REFERENCES `medico` (`id_medico`);

--
-- Filtros para la tabla `medico`
--
ALTER TABLE `medico`
  ADD CONSTRAINT `medico_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `paciente`
--
ALTER TABLE `paciente`
  ADD CONSTRAINT `paciente_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`);

--
-- Filtros para la tabla `receta`
--
ALTER TABLE `receta`
  ADD CONSTRAINT `receta_ibfk_1` FOREIGN KEY (`id_consulta`) REFERENCES `consulta` (`id_consulta`);
COMMIT;


-- =====================================================================
-- Núcleo clínico: triaje, antecedentes, auditoría y recuperación de clave
-- =====================================================================
CREATE TABLE IF NOT EXISTS `signos_vitales` (
  `id_signos` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_cita` int(11) DEFAULT NULL,
  `id_paciente_usuario` int(11) NOT NULL,
  `presion_sistolica` smallint(6) DEFAULT NULL,
  `presion_diastolica` smallint(6) DEFAULT NULL,
  `frecuencia_cardiaca` smallint(6) DEFAULT NULL,
  `frecuencia_respiratoria` smallint(6) DEFAULT NULL,
  `temperatura` decimal(4,1) DEFAULT NULL,
  `saturacion` tinyint(4) DEFAULT NULL,
  `peso` decimal(5,1) DEFAULT NULL,
  `talla` smallint(6) DEFAULT NULL,
  `imc` decimal(4,1) DEFAULT NULL,
  `glucometria` smallint(6) DEFAULT NULL,
  `dolor` tinyint(4) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `registrado_por` int(11) DEFAULT NULL,
  `nombre_registro` varchar(120) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  KEY `idx_signos_cita` (`id_cita`),
  KEY `idx_signos_paciente` (`id_paciente_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `antecedentes_paciente` (
  `id_paciente_usuario` int(11) NOT NULL PRIMARY KEY,
  `grupo_sanguineo` varchar(5) DEFAULT NULL,
  `alergias` text DEFAULT NULL,
  `enfermedades_cronicas` text DEFAULT NULL,
  `medicamentos_habituales` text DEFAULT NULL,
  `antecedentes_quirurgicos` text DEFAULT NULL,
  `antecedentes_familiares` text DEFAULT NULL,
  `actualizado_por` varchar(120) DEFAULT NULL,
  `fecha_actualizacion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `antecedentes_paciente` (`id_paciente_usuario`, `grupo_sanguineo`, `alergias`, `enfermedades_cronicas`, `medicamentos_habituales`, `antecedentes_quirurgicos`, `antecedentes_familiares`, `actualizado_por`, `fecha_actualizacion`) VALUES
(5, 'O+', 'Penicilina', 'Hipotiroidismo', 'Levotiroxina 50 mcg', 'Apendicectomía (2015)', 'Madre con diabetes tipo 2', 'Sistema (demostración)', '2026-01-01 08:00:00');

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id_auditoria` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` int(11) DEFAULT NULL,
  `rol` varchar(20) DEFAULT NULL,
  `nombre` varchar(120) DEFAULT NULL,
  `accion` varchar(60) NOT NULL,
  `detalle` varchar(500) DEFAULT NULL,
  `entidad` varchar(40) DEFAULT NULL,
  `id_entidad` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  KEY `idx_aud_fecha` (`fecha`),
  KEY `idx_aud_usuario` (`id_usuario`),
  KEY `idx_aud_entidad` (`entidad`, `id_entidad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `recuperacion_clave` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expira` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `creado` datetime NOT NULL DEFAULT current_timestamp(),
  UNIQUE KEY `uk_token` (`token_hash`),
  KEY `idx_rec_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `cita` ADD COLUMN `recordatorio_enviado` tinyint(1) NOT NULL DEFAULT 0;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
