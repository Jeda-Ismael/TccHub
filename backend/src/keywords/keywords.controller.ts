import { Body, Controller, Delete, Get, Param, Post, Put, UseGuards } from '@nestjs/common';
import { Role } from '@prisma/client';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { RolesGuard } from '../common/guards/roles.guard';
import { Roles } from '../common/decorators/roles.decorator';
import { KeywordsService } from './keywords.service';
import { KeywordDto } from './dto/keyword.dto';

@Controller('keywords')
@UseGuards(JwtAuthGuard, RolesGuard)
export class KeywordsController {
  constructor(private service: KeywordsService) {}

  @Get()
  findAll() {
    return this.service.findAll();
  }

  @Post()
  @Roles(Role.COORDINATION)
  create(@Body() dto: KeywordDto) {
    return this.service.create(dto);
  }

  @Put(':id')
  @Roles(Role.COORDINATION)
  update(@Param('id') id: string, @Body() dto: KeywordDto) {
    return this.service.update(id, dto);
  }

  @Delete(':id')
  @Roles(Role.COORDINATION)
  remove(@Param('id') id: string) {
    return this.service.remove(id);
  }
}
