import { IsArray, IsBoolean, IsOptional, IsString, MinLength } from 'class-validator';

export class UpdateTccDto {
  @IsOptional()
  @IsString()
  @MinLength(5)
  title?: string;

  @IsOptional()
  @IsString()
  @MinLength(20)
  abstract?: string;

  @IsOptional()
  @IsString()
  advisorId?: string;

  @IsOptional()
  @IsArray()
  thematicAreaIds?: string[];

  @IsOptional()
  @IsArray()
  keywordIds?: string[];

  @IsOptional()
  @IsBoolean()
  publishable?: boolean;
}
