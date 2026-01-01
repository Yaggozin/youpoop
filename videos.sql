-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 16/11/2025 às 12:24
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
-- Estrutura para tabela `videos`
--

CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_path` varchar(255) NOT NULL COMMENT 'Caminho para o arquivo de vídeo no servidor',
  `thumbnail_path` varchar(255) NOT NULL COMMENT 'Caminho para a thumbnail no servidor',
  `duration` varchar(10) DEFAULT '00:00' COMMENT 'Duração formatada do vídeo (Ex: 05:12)',
  `visibility` enum('public','unlisted','private') NOT NULL DEFAULT 'public',
  `upload_date` datetime DEFAULT current_timestamp(),
  `views` int(11) DEFAULT 0,
  `rating_sum` int(11) NOT NULL DEFAULT 0,
  `rating_count` int(11) NOT NULL DEFAULT 0,
  `average_rating` decimal(2,1) NOT NULL DEFAULT 0.0,
  `category` varchar(50) NOT NULL DEFAULT 'none',
  `tags` text DEFAULT NULL,
  `comment_options` varchar(15) NOT NULL DEFAULT 'allow',
  `comment_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `videos`
--

INSERT INTO `videos` (`id`, `user_id`, `title`, `description`, `video_path`, `thumbnail_path`, `duration`, `visibility`, `upload_date`, `views`, `rating_sum`, `rating_count`, `average_rating`, `category`, `tags`, `comment_options`, `comment_count`) VALUES
(1, 1, 'Este Vídeo.', 'Esse é o primeiro vídeo enviado para a rede social YouPoop :D', 'uploads/videos/68e3dae5e5c7b4.45281717.mp4', 'uploads/thumbnails/68e3dae5e5c7b4.45281717.png', '0:11', 'public', '2025-10-06 12:06:13', 30, 0, 1, 5.0, 'none', NULL, 'allow', 3),
(3, 1, 'Teste2.mp4', 'woow', 'uploads/videos/68e3f187b7d840.84589215.mp4', 'uploads/thumbnails/68e3f187b7d840.84589215.png', '0:11', 'public', '2025-10-06 13:42:47', 1, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(6, 2, '(REUPLOAD) The Tropical Obby In Roblox.mp4', 'reupload', 'uploads/videos/68e4ed9ca9a9b3.86554292.mp4', 'uploads/thumbnails/68e4ed9ca9a9b3.86554292.png', '1:53', 'public', '2025-10-07 07:38:20', 4, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(7, 4, 'YTPBR - Madrugalizated', 'meu primeiro ytpmv', 'uploads/videos/68e5043ba3db85.25905749.mp4', 'uploads/thumbnails/68e5043ba3db85.25905749.png', '0:28', 'public', '2025-10-07 09:14:51', 6, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(8, 4, 'YTPMV - Marquino My DJ', 'faz o sample de guitarra', 'uploads/videos/68e50978b9a0a0.19954197.mp4', 'uploads/thumbnails/68e50978b9a0a0.19954197.png', '0:10', 'public', '2025-10-07 09:37:12', 3, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(9, 4, '(REUPLOAD) YTPMV - Seu Madruga Will Go On', 'reupload do canal mestre3224.\r\n\r\nDESCRIÇÃO ORIGINAL:\r\n\r\nEspecial de quase 2000 subs.\r\n\r\nDATA DE ENVIO:\r\nSep 26, 2011', 'uploads/videos/68e50ec5a4b4e1.97545636.mp4', 'uploads/thumbnails/68e50ec5a4b4e1.97545636.png', '3:26', 'public', '2025-10-07 09:59:49', 10, 0, 0, 0.0, 'none', NULL, 'allow', 1),
(10, 2, '(REUPLOAD) CellBit Vs Mussoumano I Batalha de Youtubers', '► Inscreva-se no canal: https://goo.gl/A0D5pX\r\n\r\nCELLBITS ►http://goo.gl/gKy7Op◄\r\n\r\nA Batalha de Youtubers está de volta! A série mais amada de todo o Youtube brasileiro voltou com força total. Dessa vez quem desafiou o Mussoumano foi o Youtuber Rafael Lange do canal \"CellBits\".\r\n\r\nInsta: http://instagram.com/Mussoumano\r\nFace: http://facebook.com/Mussoumano\r\nTwitter: http://twitter.com/Mussoumano\r\n\r\nContato profissional:\r\nmussoumano@gmail.com', 'uploads/videos/68e5a7aee393b1.74867487.mp4', 'uploads/thumbnails/68e5a7aee393b1.74867487.png', '2:19', 'public', '2025-10-07 20:52:15', 6, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(12, 4, 'Yagg0zin - Turn Down My Music', 'yet.', 'uploads/videos/68e99c95be8b04.19329170.mp4', 'uploads/thumbnails/68e99c95be8b04.19329170.png', '2:54', 'public', '2025-10-10 20:53:57', 3, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(13, 1, 'Como baixar videos do youtube.com de graça', 'desculpe a baixa qualidade.', 'uploads/videos/68e9c97edd9cd4.47947515.mp4', 'uploads/thumbnails/68e9c97edd9cd4.47947515.png', '15:13', 'public', '2025-10-11 00:05:34', 2, 0, 0, 0.0, 'none', NULL, 'allow', 0),
(14, 1, 'Como baixar videos do youtube.com de graça (HD)', 'agora em hd.', 'uploads/videos/68e9c9b153ce45.40251942.mp4', 'uploads/thumbnails/68e9c9b153ce45.40251942.png', '15:13', 'public', '2025-10-11 00:06:25', 3, 0, 1, 5.0, 'none', NULL, 'allow', 0),
(15, 4, 'Video para o YouPoop', 'video para o youpoop.\r\naperta no video ai :D', 'uploads/videos/68ea62c8ab47f0.82168095.mp4', 'uploads/thumbnails/68ea62c8ab47f0.82168095.png', '3:27', 'public', '2025-10-11 10:59:36', 25, 4, 2, 5.0, 'none', NULL, 'allow', 2);

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
