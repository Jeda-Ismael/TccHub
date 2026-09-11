import { IsDateString, IsEnum, IsString } from 'class-validator';
import { PeriodType } from '@prisma/client';

export class PeriodDto {
  @IsEnum(PeriodType)
  type: PeriodType;

  @IsString()
  label: string;

  @IsDateString()
  startDate: string;

  @IsDateString()
  endDate: string;
}
