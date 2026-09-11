-- CreateTable
CREATE TABLE `users` (
    `id` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `passwordHash` VARCHAR(191) NULL,
    `googleId` VARCHAR(191) NULL,
    `role` ENUM('STUDENT', 'ADVISOR', 'EVALUATOR', 'COORDINATION') NOT NULL DEFAULT 'STUDENT',
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `users_email_key`(`email`),
    UNIQUE INDEX `users_googleId_key`(`googleId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tccs` (
    `id` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `abstract` TEXT NOT NULL,
    `status` ENUM('DRAFT', 'SUBMITTED', 'ADVISOR_REVIEW', 'SENT_TO_EVALUATION', 'CORRECTIONS_REQUESTED', 'RESUBMITTED', 'ADVISOR_FINAL_REVIEW', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'DRAFT',
    `publishable` BOOLEAN NOT NULL DEFAULT false,
    `projectGrade` DOUBLE NULL,
    `presentationGrade` DOUBLE NULL,
    `documentUrl` VARCHAR(191) NULL,
    `documentVersion` INTEGER NOT NULL DEFAULT 0,
    `studentId` VARCHAR(191) NOT NULL,
    `advisorId` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `thematic_areas` (
    `id` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,

    UNIQUE INDEX `thematic_areas_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tcc_thematic_areas` (
    `tccId` VARCHAR(191) NOT NULL,
    `thematicAreaId` VARCHAR(191) NOT NULL,

    PRIMARY KEY (`tccId`, `thematicAreaId`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `keywords` (
    `id` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,

    UNIQUE INDEX `keywords_name_key`(`name`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tcc_keywords` (
    `tccId` VARCHAR(191) NOT NULL,
    `keywordId` VARCHAR(191) NOT NULL,

    PRIMARY KEY (`tccId`, `keywordId`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tcc_evaluators` (
    `id` VARCHAR(191) NOT NULL,
    `tccId` VARCHAR(191) NOT NULL,
    `evaluatorId` VARCHAR(191) NOT NULL,
    `verdict` ENUM('PENDING', 'APPROVED', 'CORRECTIONS_REQUESTED') NOT NULL DEFAULT 'PENDING',
    `comment` TEXT NULL,
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `tcc_evaluators_tccId_evaluatorId_key`(`tccId`, `evaluatorId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `submission_periods` (
    `id` VARCHAR(191) NOT NULL,
    `type` ENUM('SUBMISSION', 'EVALUATION') NOT NULL,
    `label` VARCHAR(191) NOT NULL,
    `startDate` DATETIME(3) NOT NULL,
    `endDate` DATETIME(3) NOT NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `tcc_status_history` (
    `id` VARCHAR(191) NOT NULL,
    `tccId` VARCHAR(191) NOT NULL,
    `fromStatus` ENUM('DRAFT', 'SUBMITTED', 'ADVISOR_REVIEW', 'SENT_TO_EVALUATION', 'CORRECTIONS_REQUESTED', 'RESUBMITTED', 'ADVISOR_FINAL_REVIEW', 'APPROVED', 'REJECTED') NULL,
    `toStatus` ENUM('DRAFT', 'SUBMITTED', 'ADVISOR_REVIEW', 'SENT_TO_EVALUATION', 'CORRECTIONS_REQUESTED', 'RESUBMITTED', 'ADVISOR_FINAL_REVIEW', 'APPROVED', 'REJECTED') NOT NULL,
    `comment` TEXT NULL,
    `changedById` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `tccs` ADD CONSTRAINT `tccs_studentId_fkey` FOREIGN KEY (`studentId`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tccs` ADD CONSTRAINT `tccs_advisorId_fkey` FOREIGN KEY (`advisorId`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_thematic_areas` ADD CONSTRAINT `tcc_thematic_areas_tccId_fkey` FOREIGN KEY (`tccId`) REFERENCES `tccs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_thematic_areas` ADD CONSTRAINT `tcc_thematic_areas_thematicAreaId_fkey` FOREIGN KEY (`thematicAreaId`) REFERENCES `thematic_areas`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_keywords` ADD CONSTRAINT `tcc_keywords_tccId_fkey` FOREIGN KEY (`tccId`) REFERENCES `tccs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_keywords` ADD CONSTRAINT `tcc_keywords_keywordId_fkey` FOREIGN KEY (`keywordId`) REFERENCES `keywords`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_evaluators` ADD CONSTRAINT `tcc_evaluators_tccId_fkey` FOREIGN KEY (`tccId`) REFERENCES `tccs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_evaluators` ADD CONSTRAINT `tcc_evaluators_evaluatorId_fkey` FOREIGN KEY (`evaluatorId`) REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_status_history` ADD CONSTRAINT `tcc_status_history_tccId_fkey` FOREIGN KEY (`tccId`) REFERENCES `tccs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `tcc_status_history` ADD CONSTRAINT `tcc_status_history_changedById_fkey` FOREIGN KEY (`changedById`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;
