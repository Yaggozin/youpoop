-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 21/12/2025 às 13:01
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `ytp_db`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_icon_path` varchar(255) DEFAULT NULL,
  `channel_slogan` varchar(255) DEFAULT NULL,
  `channel_banner_path` varchar(255) DEFAULT NULL,
  `channel_background_path` varchar(255) DEFAULT NULL,
  `color_quadradop` varchar(7) DEFAULT '#FFFFFF',
  `color_left_section` varchar(7) DEFAULT '#F0F0F0',
  `color_upper_section` varchar(7) DEFAULT '#FFFFFF',
  `color_sections` varchar(7) DEFAULT '#FFFFFF',
  `channel_font` varchar(100) DEFAULT 'Arial, sans-serif',
  `join_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `layout_mode` varchar(10) NOT NULL DEFAULT '2011',
  `channel_sections_config` text DEFAULT NULL,
  `banner_position_y` varchar(5) DEFAULT '50%',
  `full_name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT '',
  `customization` longtext DEFAULT NULL,
  `featured_video_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `created_at`, `profile_icon_path`, `channel_slogan`, `channel_banner_path`, `channel_background_path`, `color_quadradop`, `color_left_section`, `color_upper_section`, `color_sections`, `channel_font`, `join_date`, `layout_mode`, `channel_sections_config`, `banner_position_y`, `full_name`, `location`, `customization`, `featured_video_id`) VALUES
(1, 'Hello World', 'aleatoriedadelegall@gmail.com', '$2y$10$h1pllTkPBLvEYWH589YQEeQLdHHcKWaO4pmc5mYkbrYBrj/sdLlV2', '2025-10-05 23:49:05', 'uploads/icons/1_icon.png', 'o primeiro canal do youpoop', 'https://cdn.wallpapersafari.com/16/96/a6fm9R.jpg', NULL, '#FFFFFF', '#F0F0F0', '#FFFFFF', '#FFFFFF', 'Arial, sans-serif', '2025-10-09 22:03:42', '2011', '[{\"type\":\"main_video\",\"params\":[]}]', '50%', '', '', NULL, NULL),
(2, 'Usuario', 'trescaras50@gmail.com', '$2y$10$3nxFiN58L4wLPBfRYrVDoOGXbC/.4Lwn59Yat5qYTwI82UG06u8Ba', '2025-10-06 18:50:49', NULL, NULL, 'uploads/banners/2_banner_1761411744.jpg', NULL, '#FFFFFF', '#F0F0F0', '#FFFFFF', '#FFFFFF', 'Arial, sans-serif', '2025-10-09 22:03:42', '2013', '[{\"type\":\"main_video\",\"params\":{\"video_id\":6}},{\"type\":\"recent_uploads\",\"params\":[]},{\"type\":\"main_video\",\"params\":{\"video_id\":10}}]', '50%', '', '', NULL, NULL),
(3, 'Quekk', 'pedro@gmail.com', '$2y$10$NctEHBXyHi5QeIJZjRViOu8idum/CGKfe/feYKKdiI.VItl2TldXm', '2015-10-16 18:50:49', NULL, NULL, NULL, NULL, '#FFFFFF', '#F0F0F0', '#FFFFFF', '#FFFFFF', 'Arial, sans-serif', '2025-12-04 15:26:20', '2011', NULL, '50%', '', '', NULL, NULL),
(4, 'YahGo', 'lastgatao@gmail.com', '$2y$10$SQLPABjFW66A2oEG1xab6.aEiRvulZq1nz4OtoEm7xxXLHxCK0KTK', '2025-10-07 12:10:05', 'uploads/icons/4_icon.png', 'olá, meu nome é yago, mas o nome do meu canal é yahgo, enfim...faço ytpbr e mvs (as vezes)', 'https://files.catbox.moe/nt3h1k.png', 'https://files.catbox.moe/10hrnl.png', '#7C3DDB', '#F0F0F0', '#FFFFFF', '#FFFFFF', 'Arial, sans-serif', '2025-10-09 22:03:42', '2013', '[{\"type\":\"main_video\",\"params\":{\"video_id\":15}},{\"type\":\"recent_uploads\",\"params\":[]},{\"type\":\"playlists\",\"params\":[]}]', '50%', '', '', '{\"banner_map\":\"<area target=\\\"\\\" alt=\\\"obey weegee\\\" title=\\\"obey weegee\\\" href=\\\"\\/weegee.php\\\" coords=\\\"1,199,1245,1\\\" shape=\\\"rect\\\">\",\"colors\":{\"involucro\":\"#699c61\",\"topbar\":\"#5e8347\",\"principal\":\"#ffffff\",\"texto\":\"#000000\",\"body\":\"#ece7bb\",\"transparency\":\"true\"}}', 7),
(5, 'YouPoop Oficial', 'youpoop@tutamail.com', '$2y$10$qe8/fsAdymaOw5SZE7r9SuN6rtcz1/Yik5D0xylCCsGqkBtnmjobS', '2025-10-08 23:27:56', 'uploads/icons/5_icon.png', 'YouPoop Oficial Channel.', 'https://files.catbox.moe/t566vu.png', 'https://files.catbox.moe/d306mu.png', '#FFFFFF', '#F0F0F0', '#FFFFFF', '#FFFFFF', 'Arial, sans-serif', '2025-10-09 22:03:42', '2011', NULL, '50%', '', '', '{\"banner_map\":\"<area target=\\\"\\\" alt=\\\"obey weegee\\\" title=\\\"obey weegee\\\" href=\\\"\\/weegee.php\\\" coords=\\\"1,199,1245,1\\\" shape=\\\"rect\\\">\",\"colors\":{\"involucro\":\"#ff6468\",\"topbar\":\"#ce0005\",\"principal\":\"#ffdfe0\",\"texto\":\"#040000\",\"body\":\"#ffffff\",\"transparency\":\"false\"}}', NULL),
(6, 'TaAzedoPraCacete', 'Azeduhehe@proton.me', '$2y$10$GNLu4qHw85HRY7mS/JX5B.e5FrEpZvu/Ko87uvrGuBYDWxHgSrNdq', '2025-11-08 22:18:00', NULL, NULL, NULL, NULL, '#FFFFFF', '#F0F0F0', '#FFFFFF', '#FFFFFF', 'Arial, sans-serif', '2025-11-08 22:18:00', '2011', '[{\"type\":\"recent_uploads\",\"params\":[]}]', '50%', '', '', NULL, NULL);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
