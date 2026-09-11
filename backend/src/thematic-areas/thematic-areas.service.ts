import { BadRequestException, ConflictException, Injectable } from '@nestjs/common';
import { Prisma } from '@prisma/client';
import { PrismaService } from '../prisma/prisma.service';
import { ThematicAreaDto } from './dto/thematic-area.dto';

@Injectable()
export class ThematicAreasService {
  constructor(private prisma: PrismaService) {}

  findAll() {
    return this.prisma.thematicArea.findMany({
      orderBy: { name: 'asc' },
      include: { _count: { select: { tccs: true } } },
    });
  }

  async create(dto: ThematicAreaDto) {
    await this.assertNameAvailable(dto.name);
    return this.runUniqueSafe(() => this.prisma.thematicArea.create({ data: { name: dto.name } }));
  }

  async update(id: string, dto: ThematicAreaDto) {
    await this.assertNameAvailable(dto.name, id);
    return this.runUniqueSafe(() => this.prisma.thematicArea.update({ where: { id }, data: { name: dto.name } }));
  }

  async remove(id: string) {
    const usageCount = await this.prisma.tccThematicArea.count({ where: { thematicAreaId: id } });
    if (usageCount > 0) {
      throw new BadRequestException(
        `Não é possível excluir: ${usageCount} TCC(s) usam esta área temática. Remova a associação nos TCCs antes.`,
      );
    }
    return this.prisma.thematicArea.delete({ where: { id } });
  }

  // Checagem prévia (o collation padrão do MySQL já compara sem diferenciar
  // maiúsculas/minúsculas) — evita a corrida entre checar e gravar dar erro
  // cru de constraint em vez de uma mensagem amigável.
  private async assertNameAvailable(name: string, excludingId?: string) {
    const existing = await this.prisma.thematicArea.findFirst({ where: { name } });
    if (existing && existing.id !== excludingId) {
      throw new ConflictException('Já existe uma área temática com este nome.');
    }
  }

  // Rede de segurança contra a corrida entre a checagem acima e a gravação:
  // se ainda assim colidir no banco, converte o erro cru do Prisma numa
  // mensagem amigável em vez de vazar um 500.
  private async runUniqueSafe<T>(fn: () => Promise<T>): Promise<T> {
    try {
      return await fn();
    } catch (err) {
      if (err instanceof Prisma.PrismaClientKnownRequestError && err.code === 'P2002') {
        throw new ConflictException('Já existe uma área temática com este nome.');
      }
      throw err;
    }
  }
}
