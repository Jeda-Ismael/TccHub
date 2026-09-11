import { IsArray, IsOptional, IsString, MinLength } from 'class-validator';

export class CreateTccDto {
  @IsString()
  @MinLength(5)
  title: string;

  @IsString()
  @MinLength(20)
  abstract: string;

  @IsOptional()
  @IsString()
  advisorId?: string;

  @IsOptional()
  @IsArray()
  thematicAreaIds?: string[];

  @IsOptional()
  @IsArray()
  keywordIds?: string[];
}
