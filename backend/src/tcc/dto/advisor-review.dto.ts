import { IsBoolean, IsOptional, IsString } from 'class-validator';

export class AdvisorReviewDto {
  @IsBoolean()
  approve: boolean;

  @IsOptional()
  @IsString()
  comment?: string;
}
