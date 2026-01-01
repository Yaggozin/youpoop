-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 17/11/2025 às 18:40
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
-- Estrutura para tabela `playlist_videos`
--

CREATE TABLE `playlist_videos` (
  `id` int(11) NOT NULL,
  `playlist_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `position` int(11) NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `playlist_videos`
--

INSERT INTO `playlist_videos` (`id`, `playlist_id`, `video_id`, `position`, `added_at`) VALUES
(1, 1, 15, 1, '2025-11-14 15:36:17'),
(2, 1, 1, 2, '2025-11-14 15:51:50'),
(3, 1, 14, 3, '2025-11-15 19:55:33');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `playlist_videos`
--
ALTER TABLE `playlist_videos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_playlist_video` (`playlist_id`,`video_id`),
  ADD KEY `video_id` (`video_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `playlist_videos`
--
ALTER TABLE `playlist_videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `playlist_videos`
--
ALTER TABLE `playlist_videos`
  ADD CONSTRAINT `playlist_videos_ibfk_1` FOREIGN KEY (`playlist_id`) REFERENCES `playlists` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `playlist_videos_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
