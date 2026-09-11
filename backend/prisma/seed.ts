import { PrismaClient, Role, PeriodType } from '@prisma/client';
import * as bcrypt from 'bcrypt';

const prisma = new PrismaClient();

async function upsertUser(name: string, email: string, role: Role) {
  const passwordHash = await bcrypt.hash('123456', 10);
  return prisma.user.upsert({
    where: { email },
    update: {},
    create: { name, email, passwordHash, role },
  });
}

async function main() {
  const student = await upsertUser('Aluno Teste', 'aluno@hubtcc.dev', Role.STUDENT);
  const advisor = await upsertUser('Orientador Teste', 'orientador@hubtcc.dev', Role.ADVISOR);
  const evaluator1 = await upsertUser('Avaliador Um', 'avaliador1@hubtcc.dev', Role.EVALUATOR);
  const evaluator2 = await upsertUser('Avaliador Dois', 'avaliador2@hubtcc.dev', Role.EVALUATOR);
  await upsertUser('Coordenação Teste', 'coordenacao@hubtcc.dev', Role.COORDINATION);

  const area = await prisma.thematicArea.upsert({
    where: { name: 'Engenharia de Software' },
    update: {},
    create: { name: 'Engenharia de Software' },
  });

  const keyword = await prisma.keyword.upsert({
    where: { name: 'Sistemas Web' },
    update: {},
    create: { name: 'Sistemas Web' },
  });

  const now = new Date();
  const inOneYear = new Date(now.getFullYear() + 1, now.getMonth(), now.getDate());

  await prisma.submissionPeriod.create({
    data: {
      type: PeriodType.SUBMISSION,
      label: `${now.getFullYear()}/1`,
      startDate: now,
      endDate: inOneYear,
    },
  });
  await prisma.submissionPeriod.create({
    data: {
      type: PeriodType.EVALUATION,
      label: `${now.getFullYear()}/1`,
      startDate: now,
      endDate: inOneYear,
    },
  });

  console.log('Seed concluído:', {
    student: student.email,
    advisor: advisor.email,
    evaluator1: evaluator1.email,
    evaluator2: evaluator2.email,
    area: area.name,
    keyword: keyword.name,
  });
  console.log('Senha padrão de todos os usuários de teste: 123456');
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
