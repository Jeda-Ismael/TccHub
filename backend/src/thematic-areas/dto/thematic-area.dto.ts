import { IsString, MinLength } from 'class-validator';

export class ThematicAreaDto {
  @IsString()
  @MinLength(2)
  name: string;
}
