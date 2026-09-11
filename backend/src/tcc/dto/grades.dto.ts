import { IsNumber, Max, Min } from 'class-validator';

export class GradesDto {
  @IsNumber()
  @Min(0)
  @Max(10)
  projectGrade: number;

  @IsNumber()
  @Min(0)
  @Max(10)
  presentationGrade: number;
}
