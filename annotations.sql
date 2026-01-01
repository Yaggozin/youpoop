-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 08/12/2025 às 16:36
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
-- Estrutura para tabela `annotations`
--

CREATE TABLE `annotations` (
  `id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `type` enum('speech','note','title','spotlight','label') NOT NULL,
  `text_content` varchar(255) NOT NULL,
  `start_time_sec` decimal(10,3) NOT NULL COMMENT 'Tempo de início da anotação no vídeo (em segundos)',
  `end_time_sec` decimal(10,3) NOT NULL COMMENT 'Tempo final da anotação no vídeo (em segundos)',
  `x_pos` decimal(5,2) NOT NULL COMMENT 'Posição X (em % da largura do player)',
  `y_pos` decimal(5,2) NOT NULL COMMENT 'Posição Y (em % da altura do player)',
  `width` decimal(5,2) NOT NULL COMMENT 'Largura da anotação (em % da largura do player)',
  `height` decimal(5,2) NOT NULL COMMENT 'Altura da anotação (em % da altura do player)',
  `link_url` varchar(255) DEFAULT NULL COMMENT 'URL para linkar a anotação',
  `link_video_id` int(11) DEFAULT NULL COMMENT 'ID do vídeo interno para linkar',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `color` varchar(20) DEFAULT '#000000',
  `font_size` varchar(10) DEFAULT '14px'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `annotations`
--

INSERT INTO `annotations` (`id`, `video_id`, `type`, `text_content`, `start_time_sec`, `end_time_sec`, `x_pos`, `y_pos`, `width`, `height`, `link_url`, `link_video_id`, `is_active`, `color`, `font_size`) VALUES
(3, 15, 'note', 'Assista esse vídeo!', 129.000, 201.000, 5.40, 14.93, 45.94, 45.58, 'http://youpoop.local/assistir.php?id=12', NULL, 1, '#e74c3c', '30px');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `annotations`
--
ALTER TABLE `annotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_id` (`video_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `annotations`
--
ALTER TABLE `annotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `annotations`
--
ALTER TABLE `annotations`
  ADD CONSTRAINT `annotations_ibfk_1` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
