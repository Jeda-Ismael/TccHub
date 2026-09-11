import { Injectable } from '@nestjs/common';
import { PeriodType } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import { PeriodDto } from './dto/period.dto';

@Injectable()
export class PeriodsService {
  constructor(private prisma: PrismaService) {}

  findAll() {
    return this.prisma.submissionPeriod.findMany({ orderBy: { startDate: 'desc' } });
  }

  create(dto: PeriodDto) {
    return this.prisma.submissionPeriod.create({
      data: {
        type: dto.type,
        label: dto.label,
        startDate: new Date(dto.startDate),
        endDate: new Date(dto.endDate),
      },
    });
  }

  update(id: string, dto: PeriodDto) {
    return this.prisma.submissionPeriod.update({
      where: { id },
      data: {
        type: dto.type,
        label: dto.label,
        startDate: new Date(dto.startDate),
        endDate: new Date(dto.endDate),
      },
    });
  }

  remove(id: string) {
    return this.prisma.submissionPeriod.delete({ where: { id } });
  }

  async isPeriodActive(type: PeriodType): Promise<boolean> {
    const now = new Date();
    const period = await this.prisma.submissionPeriod.findFirst({
      where: { type, startDate: { lte: now }, endDate: { gte: now } },
    });
    return !!period;
  }
}
