import { IsString, MinLength } from 'class-validator';

export class KeywordDto {
  @IsString()
  @MinLength(2)
  name: string;
}
