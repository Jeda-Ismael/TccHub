import { IsEnum, IsOptional, IsString, MinLength, ValidateIf } from 'class-validator';
import { EvaluationVerdict } from '@prisma/client';

export class EvaluateDto {
  @IsEnum(EvaluationVerdict)
  verdict: EvaluationVerdict;

  // Obrigatório ao solicitar correção: sem isso o aluno não recebe nenhuma
  // explicação do que precisa ajustar. Quando o veredito é aprovação, o
  // ValidateIf abaixo pula a validação e o campo permanece opcional.
  @ValidateIf((dto) => dto.verdict === EvaluationVerdict.CORRECTIONS_REQUESTED)
  @IsString()
  @MinLength(5, { message: 'É necessário explicar o que precisa ser corrigido (mínimo 5 caracteres)' })
  comment?: string;
}
