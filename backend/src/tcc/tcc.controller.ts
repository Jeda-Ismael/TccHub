import {
  Body,
  Controller,
  Get,
  Param,
  Patch,
  Post,
  Put,
  UploadedFile,
  UseGuards,
  UseInterceptors,
} from '@nestjs/common';
import { FileInterceptor } from '@nestjs/platform-express';
import { diskStorage } from 'multer';
import { extname } from 'path';
import { BadRequestException } from '@nestjs/common';
import { Role } from '@prisma/client';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { RolesGuard } from '../common/guards/roles.guard';
import { Roles } from '../common/decorators/roles.decorator';
import { CurrentUser } from '../common/decorators/current-user.decorator';
import { TccService } from './tcc.service';
import { CreateTccDto } from './dto/create-tcc.dto';
import { UpdateTccDto } from './dto/update-tcc.dto';
import { AdvisorReviewDto } from './dto/advisor-review.dto';
import { EvaluateDto } from './dto/evaluate.dto';
import { GradesDto } from './dto/grades.dto';
import { AssignEvaluatorsDto } from './dto/assign-evaluators.dto';

@Controller('tcc')
@UseGuards(JwtAuthGuard, RolesGuard)
export class TccController {
  constructor(private tccService: TccService) {}

  @Post()
  @Roles(Role.STUDENT)
  create(@Body() dto: CreateTccDto, @CurrentUser() user: any) {
    return this.tccService.create(dto, user);
  }

  @Get()
  findAll(@CurrentUser() user: any) {
    return this.tccService.findAll(user);
  }

  @Get(':id')
  findOne(@Param('id') id: string, @CurrentUser() user: any) {
    return this.tccService.findOne(id, user);
  }

  @Patch(':id')
  update(@Param('id') id: string, @Body() dto: UpdateTccDto, @CurrentUser() user: any) {
    return this.tccService.update(id, dto, user);
  }

  @Post(':id/document')
  @Roles(Role.STUDENT)
  @UseInterceptors(
    FileInterceptor('file', {
      storage: diskStorage({
        destination: process.env.UPLOADS_DIR || 'uploads',
        filename: (_req, file, cb) => {
          const unique = `${Date.now()}-${Math.round(Math.random() * 1e9)}`;
          cb(null, `${unique}${extname(file.originalname)}`);
        },
      }),
      fileFilter: (_req, file, cb) => {
        if (file.mimetype !== 'application/pdf') {
          return cb(new BadRequestException('Apenas arquivos PDF são aceitos'), false);
        }
        cb(null, true);
      },
      limits: { fileSize: 20 * 1024 * 1024 },
    }),
  )
  uploadDocument(
    @Param('id') id: string,
    @UploadedFile() file: Express.Multer.File,
    @CurrentUser() user: any,
  ) {
    return this.tccService.uploadDocument(id, file, user);
  }

  @Post(':id/submit')
  @Roles(Role.STUDENT)
  submit(@Param('id') id: string, @CurrentUser() user: any) {
    return this.tccService.submit(id, user);
  }

  @Put(':id/evaluators')
  @Roles(Role.COORDINATION)
  assignEvaluators(@Param('id') id: string, @Body() dto: AssignEvaluatorsDto, @CurrentUser() user: any) {
    return this.tccService.assignEvaluators(id, dto.evaluatorIds, user);
  }

  @Post(':id/advisor-review')
  @Roles(Role.ADVISOR)
  advisorReview(@Param('id') id: string, @Body() dto: AdvisorReviewDto, @CurrentUser() user: any) {
    return this.tccService.advisorReview(id, dto, user);
  }

  @Post(':id/evaluate')
  @Roles(Role.EVALUATOR)
  evaluate(@Param('id') id: string, @Body() dto: EvaluateDto, @CurrentUser() user: any) {
    return this.tccService.evaluate(id, dto, user);
  }

  @Post(':id/grades')
  @Roles(Role.ADVISOR)
  setGrades(@Param('id') id: string, @Body() dto: GradesDto, @CurrentUser() user: any) {
    return this.tccService.setGrades(id, dto, user);
  }
}
