import { BadRequestException, ForbiddenException, Injectable, NotFoundException } from '@nestjs/common';
import { EvaluationVerdict, PeriodType, Prisma, Role, TccStatus } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';

type PrismaTransaction = Prisma.TransactionClient;
import { PeriodsService } from '../periods/periods.service';
import { StorageService } from '../storage/storage.service';
import { CreateTccDto } from './dto/create-tcc.dto';
import { UpdateTccDto } from './dto/update-tcc.dto';
import { AdvisorReviewDto } from './dto/advisor-review.dto';
import { EvaluateDto } from './dto/evaluate.dto';
import { GradesDto } from './dto/grades.dto';

const TCC_INCLUDE = {
  student: { select: { id: true, name: true, email: true } },
  advisor: { select: { id: true, name: true, email: true } },
  thematicAreas: { include: { thematicArea: true } },
  keywords: { include: { keyword: true } },
  evaluators: { include: { evaluator: { select: { id: true, name: true, email: true } } } },
  statusHistory: {
    include: { changedBy: { select: { id: true, name: true, email: true } } },
    orderBy: { createdAt: 'asc' as const },
  },
};

interface AuthUser {
  id: string;
  role: Role;
}

@Injectable()
export class TccService {
  constructor(
    private prisma: PrismaService,
    private periods: PeriodsService,
    private storage: StorageService,
  ) {}

  private async recordHistory(
    tccId: string,
    fromStatus: TccStatus | null,
    toStatus: TccStatus,
    changedById: string,
    comment?: string,
  ) {
    await this.prisma.tccStatusHistory.create({
      data: { tccId, fromStatus: fromStatus ?? undefined, toStatus, changedById, comment },
    });
  }

  private relationsPayload(dto: CreateTccDto | UpdateTccDto) {
    const data: any = {};
    if (dto.thematicAreaIds) {
      data.thematicAreas = {
        deleteMany: {},
        create: dto.thematicAreaIds.map((thematicAreaId) => ({ thematicAreaId })),
      };
    }
    if (dto.keywordIds) {
      data.keywords = {
        deleteMany: {},
        create: dto.keywordIds.map((keywordId) => ({ keywordId })),
      };
    }
    return data;
  }

  async create(dto: CreateTccDto, user: AuthUser) {
    const submissionOpen = await this.periods.isPeriodActive(PeriodType.SUBMISSION);
    if (!submissionOpen) {
      throw new BadRequestException('Não há período de submissão de TCC ativo no momento');
    }

    const tcc = await this.prisma.tcc.create({
      data: {
        title: dto.title,
        abstract: dto.abstract,
        studentId: user.id,
        advisorId: dto.advisorId,
        ...this.relationsPayload(dto),
      },
      include: TCC_INCLUDE,
    });

    await this.recordHistory(tcc.id, null, TccStatus.DRAFT, user.id, 'TCC criado');
    return tcc;
  }

  async findAll(user: AuthUser) {
    const where =
      user.role === Role.STUDENT
        ? { studentId: user.id }
        : user.role === Role.ADVISOR
          ? { advisorId: user.id }
          : user.role === Role.EVALUATOR
            ? { evaluators: { some: { evaluatorId: user.id } } }
            : {};

    const tccs = await this.prisma.tcc.findMany({ where, include: TCC_INCLUDE, orderBy: { updatedAt: 'desc' } });
    return tccs.map((tcc) => this.maskEvaluatorsForViewer(tcc, user));
  }

  async findOne(id: string, user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id }, include: TCC_INCLUDE });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    this.assertCanView(tcc, user);
    return this.maskEvaluatorsForViewer(tcc, user);
  }

  // Avaliação cega: enquanto o próprio parecer do avaliador estiver PENDING,
  // ele não vê o parecer nem o comentário dos demais membros da banca — evita
  // viés de ancoragem entre avaliadores.
  private maskEvaluatorsForViewer<T extends { evaluators: any[] }>(tcc: T, user: AuthUser): T {
    if (user.role !== Role.EVALUATOR) return tcc;
    const own = tcc.evaluators.find((e) => e.evaluatorId === user.id);
    if (!own || own.verdict !== EvaluationVerdict.PENDING) return tcc;

    return {
      ...tcc,
      evaluators: tcc.evaluators.map((e) =>
        e.evaluatorId === user.id ? e : { ...e, verdict: EvaluationVerdict.PENDING, comment: null },
      ),
    };
  }

  private assertCanView(tcc: any, user: AuthUser) {
    if (user.role === Role.COORDINATION) return;
    if (user.role === Role.STUDENT && tcc.studentId === user.id) return;
    if (user.role === Role.ADVISOR && tcc.advisorId === user.id) return;
    if (user.role === Role.EVALUATOR && tcc.evaluators.some((e: any) => e.evaluatorId === user.id)) return;
    throw new ForbiddenException('Você não tem acesso a este TCC');
  }

  async update(id: string, dto: UpdateTccDto, user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }

    const isOwner = user.role === Role.STUDENT && tcc.studentId === user.id;
    const isCoordination = user.role === Role.COORDINATION;

    if (!isOwner && !isCoordination) {
      throw new ForbiddenException('Você não pode editar este TCC');
    }

    const editableStatuses: TccStatus[] = [TccStatus.DRAFT, TccStatus.CORRECTIONS_REQUESTED];
    if (isOwner && !editableStatuses.includes(tcc.status)) {
      throw new BadRequestException('O TCC só pode ser editado pelo aluno enquanto está em rascunho ou com correções solicitadas');
    }

    const { publishable, advisorId, title, abstract } = dto;
    const data: any = { ...this.relationsPayload(dto) };
    if (title !== undefined) data.title = title;
    if (abstract !== undefined) data.abstract = abstract;
    if (advisorId !== undefined) data.advisorId = advisorId;
    if (isCoordination && publishable !== undefined) data.publishable = publishable;

    return this.prisma.tcc.update({ where: { id }, data, include: TCC_INCLUDE });
  }

  async uploadDocument(id: string, file: Express.Multer.File, user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    if (tcc.studentId !== user.id) {
      throw new ForbiddenException('Apenas o aluno autor pode enviar o arquivo do TCC');
    }
    const uploadableStatuses: TccStatus[] = [TccStatus.DRAFT, TccStatus.CORRECTIONS_REQUESTED];
    if (!uploadableStatuses.includes(tcc.status)) {
      throw new BadRequestException('Só é possível enviar o arquivo enquanto o TCC está em rascunho ou com correções solicitadas');
    }

    if (tcc.documentUrl) {
      this.storage.deleteByUrl(tcc.documentUrl);
    }

    return this.prisma.tcc.update({
      where: { id },
      data: {
        documentUrl: this.storage.buildPublicUrl(file.filename),
        documentVersion: { increment: 1 },
      },
      include: TCC_INCLUDE,
    });
  }

  async submit(id: string, user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    if (tcc.studentId !== user.id) {
      throw new ForbiddenException('Apenas o aluno autor pode submeter o TCC');
    }
    if (!tcc.documentUrl) {
      throw new BadRequestException('Envie o arquivo PDF do TCC antes de submeter');
    }
    if (!tcc.advisorId) {
      throw new BadRequestException('Defina um orientador antes de submeter');
    }

    let toStatus: TccStatus;
    if (tcc.status === TccStatus.DRAFT) {
      toStatus = TccStatus.SUBMITTED;
    } else if (tcc.status === TccStatus.CORRECTIONS_REQUESTED) {
      toStatus = TccStatus.RESUBMITTED;
    } else {
      throw new BadRequestException(`Não é possível submeter um TCC no status ${tcc.status}`);
    }

    const updated = await this.prisma.tcc.update({ where: { id }, data: { status: toStatus }, include: TCC_INCLUDE });
    await this.recordHistory(id, tcc.status, toStatus, user.id);
    return updated;
  }

  async assignEvaluators(id: string, evaluatorIds: string[], user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id }, include: { evaluators: true } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    const terminalStatuses: TccStatus[] = [TccStatus.APPROVED, TccStatus.REJECTED];
    if (terminalStatuses.includes(tcc.status)) {
      throw new BadRequestException('Este TCC já foi concluído — a banca não pode mais ser alterada');
    }

    // Só adiciona/remove o que realmente mudou — recriar todos os registros
    // apagaria o parecer e o comentário já dados pelos avaliadores mantidos.
    const currentIds = tcc.evaluators.map((e) => e.evaluatorId);
    const toRemove = currentIds.filter((evaluatorId) => !evaluatorIds.includes(evaluatorId));
    const toAdd = evaluatorIds.filter((evaluatorId) => !currentIds.includes(evaluatorId));

    if (toRemove.length > 0) {
      await this.prisma.tccEvaluator.deleteMany({ where: { tccId: id, evaluatorId: { in: toRemove } } });
    }
    if (toAdd.length > 0) {
      await this.prisma.tccEvaluator.createMany({
        data: toAdd.map((evaluatorId) => ({ tccId: id, evaluatorId })),
      });
    }

    return this.prisma.tcc.findUnique({ where: { id }, include: TCC_INCLUDE });
  }

  async advisorReview(id: string, dto: AdvisorReviewDto, user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id }, include: { evaluators: true } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    if (tcc.advisorId !== user.id) {
      throw new ForbiddenException('Apenas o orientador deste TCC pode avaliá-lo');
    }

    let toStatus: TccStatus;

    const reviewableStatuses: TccStatus[] = [TccStatus.SUBMITTED, TccStatus.RESUBMITTED];
    if (reviewableStatuses.includes(tcc.status)) {
      if (dto.approve) {
        toStatus = TccStatus.SENT_TO_EVALUATION;
        if (tcc.evaluators.length > 0) {
          await this.prisma.tccEvaluator.updateMany({
            where: { tccId: id },
            data: { verdict: EvaluationVerdict.PENDING, comment: null },
          });
        }
      } else {
        toStatus = TccStatus.REJECTED;
      }
    } else if (tcc.status === TccStatus.ADVISOR_FINAL_REVIEW) {
      toStatus = dto.approve ? TccStatus.APPROVED : TccStatus.CORRECTIONS_REQUESTED;
    } else {
      throw new BadRequestException(`Não há revisão do orientador pendente para o status ${tcc.status}`);
    }

    const updated = await this.prisma.tcc.update({ where: { id }, data: { status: toStatus }, include: TCC_INCLUDE });
    await this.recordHistory(id, tcc.status, toStatus, user.id, dto.comment);
    return updated;
  }

  async evaluate(id: string, dto: EvaluateDto, user: AuthUser) {
    const evaluationOpen = await this.periods.isPeriodActive(PeriodType.EVALUATION);
    if (!evaluationOpen) {
      throw new BadRequestException('Não há período de avaliação de TCC ativo no momento');
    }

    const tcc = await this.prisma.tcc.findUnique({ where: { id }, include: { evaluators: true } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    if (tcc.status !== TccStatus.SENT_TO_EVALUATION) {
      throw new BadRequestException('Este TCC não está em avaliação pela banca no momento');
    }

    const membership = tcc.evaluators.find((e) => e.evaluatorId === user.id);
    if (!membership) {
      throw new ForbiddenException('Você não faz parte da banca deste TCC');
    }
    if (dto.verdict === EvaluationVerdict.PENDING) {
      throw new BadRequestException('Parecer inválido');
    }

    // Toda a leitura+decisão+gravação do consenso roda numa única transação:
    // se dois avaliadores votarem quase ao mesmo tempo, a segunda transação
    // só começa depois que a primeira commitou, evitando os dois lerem
    // "ainda tem pendente" e nenhum disparar a atualização de status.
    const updated = await this.prisma.$transaction(async (tx) => {
      await tx.tccEvaluator.update({
        where: { id: membership.id },
        data: { verdict: dto.verdict, comment: dto.comment },
      });

      return this.checkEvaluationConsensus(tx, id, user.id);
    });

    return this.maskEvaluatorsForViewer(updated, user);
  }

  // Regra de consenso: o status do Tcc só muda depois que TODOS os membros da
  // banca deram parecer. Se algum pediu correção, o resultado agregado é
  // CORRECTIONS_REQUESTED; só avança para revisão final do orientador se
  // todos aprovarem.
  private async checkEvaluationConsensus(tx: PrismaTransaction, tccId: string, changedById: string) {
    const evaluators = await tx.tccEvaluator.findMany({ where: { tccId } });

    const stillPending = evaluators.some((e) => e.verdict === EvaluationVerdict.PENDING);
    if (stillPending) {
      return tx.tcc.findUniqueOrThrow({ where: { id: tccId }, include: TCC_INCLUDE });
    }

    const anyCorrections = evaluators.some((e) => e.verdict === EvaluationVerdict.CORRECTIONS_REQUESTED);
    const tcc = await tx.tcc.findUniqueOrThrow({ where: { id: tccId } });
    const toStatus = anyCorrections ? TccStatus.CORRECTIONS_REQUESTED : TccStatus.ADVISOR_FINAL_REVIEW;

    const updated = await tx.tcc.update({
      where: { id: tccId },
      data: { status: toStatus },
      include: TCC_INCLUDE,
    });
    await tx.tccStatusHistory.create({
      data: {
        tccId,
        fromStatus: tcc.status,
        toStatus,
        changedById,
        comment: anyCorrections ? 'Banca concluída: correções solicitadas' : 'Banca concluída: todos aprovaram',
      },
    });
    return updated;
  }

  async setGrades(id: string, dto: GradesDto, user: AuthUser) {
    const tcc = await this.prisma.tcc.findUnique({ where: { id } });
    if (!tcc) {
      throw new NotFoundException('TCC não encontrado');
    }
    if (tcc.advisorId !== user.id) {
      throw new ForbiddenException('Apenas o orientador deste TCC pode lançar as notas');
    }

    return this.prisma.tcc.update({
      where: { id },
      data: { projectGrade: dto.projectGrade, presentationGrade: dto.presentationGrade },
      include: TCC_INCLUDE,
    });
  }
}
