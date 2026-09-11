import { ArrayMinSize, IsArray, IsString } from 'class-validator';

export class AssignEvaluatorsDto {
  @IsArray()
  @ArrayMinSize(1)
  @IsString({ each: true })
  evaluatorIds: string[];
}
