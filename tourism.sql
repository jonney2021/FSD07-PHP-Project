-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 06, 2023 at 07:24 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tourism`
--

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` int(11) NOT NULL,
  `tourPackageId` int(11) NOT NULL,
  `imageUrl` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `images`
--

INSERT INTO `images` (`id`, `tourPackageId`, `imageUrl`) VALUES
(3, 1, 'uploads/banffs_305c88a80bb2ce20c.jpeg'),
(4, 1, 'uploads/banffs_4cc479f2569945cbe.jpeg'),
(5, 1, 'uploads/banffs_1754eeb228fb321ae.jpeg'),
(6, 1, 'uploads/banffs_24a589bc2fecf56e4.jpeg'),
(7, 2, 'uploads/NewYorks_1ca294bd3e2aafa58.jpeg'),
(8, 2, 'uploads/NewYorks_292addbe231c8c515.jpeg'),
(9, 2, 'uploads/NewYorks_30bbe2c4f1266fd11.jpeg'),
(10, 2, 'uploads/NewYorks_439b650c335ae2ea2.jpeg'),
(11, 3, 'uploads/Niagaras_1e1e7fed1825e3f92.jpeg'),
(12, 3, 'uploads/Niagaras_2bc17b1ae12670969.jpeg'),
(13, 3, 'uploads/Niagaras_3b7a257cb13841334.jpeg'),
(14, 3, 'uploads/Niagaras_49f956f2c8afc486f.jpeg'),
(15, 4, 'uploads/Thailands_119f8e033f01900ef.jpeg'),
(16, 4, 'uploads/Thailands_2b6f99e61f79daaa7.jpeg'),
(17, 4, 'uploads/Thailands_3a44ad2f677fcd34f.jpeg'),
(18, 4, 'uploads/Thailands_41bc06a174946403c.jpeg'),
(19, 5, 'uploads/paris_Arc-de-Triomphea95149d09b0a3e1b.jpg'),
(20, 5, 'uploads/paris_Louvre-Museumacc08df3c14e7898.jpg'),
(21, 5, 'uploads/paris_The-Eiffel-Tower825b9fd23134a9f8.jpg'),
(22, 5, 'uploads/paris-Opera-House2fc4250611b113e8.jpg'),
(23, 6, 'uploads/beijing_greatwall_01abfb9c0d62662880.jpg'),
(24, 6, 'uploads/beijing_gugong_01d96fe9c8c8e65157.jpg'),
(25, 6, 'uploads/beijing_tiananmenac1fba3fbd171d41.jpg'),
(26, 6, 'uploads/beijing_tiantanccdc39c0f26486f8.jpg'),
(27, 7, 'uploads/tokyo_Akihabarae9c3e1c29399a3d2.jpg'),
(28, 7, 'uploads/tokyo_Cherry-Blossomsf241a49528dc615a.jpg'),
(29, 7, 'uploads/tokyo_Mount-Fujicbd05924882c7df5.jpg'),
(30, 7, 'uploads/tokyo_Sensoji-Templecca8acba1bfc2e85.jpg'),
(35, 9, 'uploads/london_1cadf254b404494f2.jpg'),
(36, 9, 'uploads/london_232d8dd7a7f3b53cd.jpg'),
(37, 9, 'uploads/london_ben_clock_2171dcfde8eec811a.jpg'),
(38, 9, 'uploads/london_St-Paul\'s-Cathedral5241d40f45985937.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `userId` int(11) NOT NULL,
  `placedTS` timestamp NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `tourPackageId` int(11) NOT NULL,
  `status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `userId`, `placedTS`, `total`, `tourPackageId`, `status`) VALUES
(55, 8, '2023-04-04 02:41:27', '2200.00', 4, 'paid'),
(56, 8, '2023-04-04 02:45:46', '600.00', 3, 'paid'),
(57, 8, '2023-04-04 02:50:47', '2200.00', 4, 'paid'),
(60, 3, '2023-04-04 16:43:52', '1800.00', 6, 'paid'),
(63, 2, '2023-04-04 23:59:26', '600.00', 3, 'unpaid'),
(64, 2, '2023-04-05 00:00:07', '1800.00', 5, 'paid'),
(65, 1, '2023-04-05 01:00:16', '2000.00', 1, 'paid');

-- --------------------------------------------------------

--
-- Table structure for table `tourpackages`
--

CREATE TABLE `tourpackages` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(100) NOT NULL,
  `location` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `details` varchar(1000) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tourpackages`
--

INSERT INTO `tourpackages` (`id`, `name`, `type`, `location`, `price`, `details`) VALUES
(1, 'Banff 6 days', 'Group Package', 'Banff', '2000.00', 'Banff & Jasper Adventure includes accommodation, meals, and more.'),
(2, 'New York Explorer 5 days', 'Couple Package', 'New York', '800.00', 'With the In-depth Cultural tour New York Explorer (5 Days),USA. '),
(3, 'Niagara Falls Weekend', 'Group Package', 'USA', '600.00', 'In-depth Cultural tour Niagara Falls Weekend taking you through Niagara.'),
(4, 'Thai Intro 9 Day', 'Family Package', 'Thailand', '2200.00', 'You have a 9 days tour package taking you through Thailand.'),
(5, '8 Day Paris', 'Group Package', 'Paris', '1800.00', '8 days itinerary trip from Paris to Paris, visiting 1 country and 4 cities'),
(6, '5 Days China Beijing', 'Family Package', 'Beijing', '1800.00', 'Tiananmen Square, Forbidden City, Temple of Heaven, Great Wall of China'),
(7, '5 Day Japan Tokyo', 'Family Package', 'Tokyo', '2000.00', 'Your epic journey through Japan blends old and new'),
(9, '8 Day London Sightseeing', 'Couple Package', 'London', '2500.00', 'History comes alive on this most epic of London trips.');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `username` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `email` varchar(320) NOT NULL,
  `phoneNo` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `username`, `password`, `email`, `phoneNo`) VALUES
(1, 'user', 'Tom', 'Tom123', 'tom@123.com', '4381111111'),
(2, 'user', 'Tommy', 'Tommy123', 'tommy@123.com', '4382222222'),
(3, 'user', 'Jim', 'Jim123', 'jim@123.com', '4383333333'),
(4, 'admin', 'Admin', 'Admin123', 'admin@admin.com', '4380000000'),
(5, 'admin', 'Yeming', 'Yeming123', 'yeming@admin.com', '4388888888'),
(6, 'user', 'Jerry', 'Jerry123', 'jerry@123.com', '5141111111'),
(7, 'user', 'James', 'James123', 'James@123.com', '5142222222'),
(8, 'user', 'Amy', 'Amy123', 'Amy@123.com', '5143333333');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tourPackageId` (`tourPackageId`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tourPackageId` (`tourPackageId`);

--
-- Indexes for table `tourpackages`
--
ALTER TABLE `tourpackages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `tourpackages`
--
ALTER TABLE `tourpackages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `images`
--
ALTER TABLE `images`
  ADD CONSTRAINT `images_ibfk_1` FOREIGN KEY (`tourPackageId`) REFERENCES `tourpackages` (`id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`tourPackageId`) REFERENCES `tourpackages` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
