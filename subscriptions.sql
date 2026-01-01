-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 17/11/2025 às 00:27
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
-- Estrutura para tabela `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `subscriber_id` int(11) NOT NULL,
  `channel_id` int(11) NOT NULL,
  `subscription_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `subscriber_id`, `channel_id`, `subscription_date`) VALUES
(7, 2, 1, '2025-10-07 07:15:35'),
(8, 1, 5, '2025-10-08 20:48:23'),
(9, 2, 5, '2025-10-08 20:48:42'),
(10, 4, 5, '2025-10-08 20:48:59'),
(12, 4, 1, '2025-10-09 14:46:51'),
(13, 5, 4, '2025-11-03 14:59:46'),
(14, 4, 2, '2025-11-04 13:38:19'),
(15, 6, 2, '2025-11-15 16:45:41'),
(16, 6, 1, '2025-11-15 18:46:04'),
(17, 5, 1, '2025-11-15 18:50:40'),
(18, 5, 2, '2025-11-15 18:50:46'),
(19, 5, 6, '2025-11-15 18:51:00'),
(20, 6, 4, '2025-11-16 10:38:07'),
(21, 6, 5, '2025-11-16 11:02:34');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_subscription` (`subscriber_id`,`channel_id`),
  ADD KEY `channel_id` (`channel_id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`subscriber_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`channel_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
