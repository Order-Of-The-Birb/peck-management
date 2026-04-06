import discord, logging
from discord.ext import commands
from datetime import datetime, UTC, timedelta
from typing import TYPE_CHECKING
if __name__ == "__main__":
	from os import path
	from sys import path as sys_path
	sys_path.append(path.abspath(path.join(path.dirname(__file__), '..')))
if TYPE_CHECKING:
	from utils.bot import Bot
# owner_only, officer_only, members_only, debug_only
from utils.bot import members_only, officer_only
import utils.generic as genericUtil
import utils.time as timeUtil
import utils.wt as wtUtil

# "utils.generic", "utils.time", "utils.wt"
__reload_deps__ = ("utils.generic", "utils.time", "utils.wt")

class SQBCog(commands.GroupCog, group_name="sqb"):
	announcement_delay = 30
	last_sqb_announcement = datetime.now(UTC)- timedelta(minutes=announcement_delay) 
	announcements = 0
	use_sqb_ping_role:bool = False
	def __init__(self, bot:'Bot'):
		self.bot = bot
		self.logger = logging.getLogger(__name__)
		self.logger.setLevel(bot.logLevel)
		self.logger.debug(f"{self.__class__.__name__} initialized")
	@discord.app_commands.command(name="announce")
	@members_only()
	@discord.app_commands.guild_only()
	async def announce_sqb(self, interaction:discord.Interaction):
		'''This command is to be used to announce SQB. Usable by squadron members'''
		await interaction.response.defer(ephemeral=True)
		current_time = datetime.now(UTC)
		start_time, end_time = timeUtil.get_sqb_timebracket()
		voice_channel = interaction.user.voice.channel if interaction.user.voice else None
		if not (self.last_sqb_announcement <= (datetime.now(UTC)- timedelta(minutes=self.announcement_delay))) and not self.bot.debug:
			await interaction.edit_original_response(content=f"Error: Last SQB ping was less than {self.announcement_delay} minutes ago.")
			return
		if not (start_time <= current_time <= end_time) and not self.bot.debug:
			await interaction.edit_original_response(content="Error: Outside of SQB time frame.")
			return
		if not (voice_channel is not None and voice_channel.id == self.bot.channelIDs["sqb"]) and not self.bot.debug:
			await interaction.edit_original_response(content=f"You have to be in <#{self.bot.channelIDs["sqb"]}> to announce SQB.")
			return
		await interaction.edit_original_response(content="Announcing SQB...")
		channel = self.bot.get_channel(self.bot.channelIDs["announcements"])
		if self.use_sqb_ping_role:
			self.announcements += 1
			role_id = 1065769588849115248
			if self.announcements >= 3:
				role_id = 919803983286128661
		else:
			role_id = 919803983286128661
		await channel.send(f"<@&{role_id}> an SQB ping was requested by {interaction.user.mention}. You can join <#{self.bot.channelIDs["sqb"]}> to play SQB.\nCurrent BR:\n# {wtUtil.sqb_br(True)[0]}\n\tTimeframe ends {timeUtil.discord_timestamp(timeUtil.toUnix(timeUtil.get_sqb_timebracket()[1]), "R")}\n\n{await genericUtil.random_propaganda(self.bot)}")
		self.last_sqb_announcement = datetime.now(UTC)
		await interaction.edit_original_response(content="SQB announced.")
		embed = discord.Embed(title="SQB Announcement", color=0x0000FF, timestamp=datetime.now(UTC), description="A member just announced SQB")
		embed.add_field(name="Username", value=f"{interaction.user.name}")
		embed.add_field(name="User ID", value=f"{interaction.user.id}")
		embed.add_field(name="User mention", value=interaction.user.mention)
		await (self.bot.get_channel(self.bot.channelIDs["spam"]).send(embed=embed))

	@discord.app_commands.command(name="plan")
	@members_only()
	@discord.app_commands.guild_only()
	async def plan_sqb(self, interaction:discord.Interaction):
		"""[Member] Plans an SQB session"""
		await interaction.response.defer(ephemeral=True)
		if self.bot.planSQB.announcementMessage is not None and not self.bot.debug:
			await interaction.edit_original_response(content=f"A member has already started planning for SQB.\nPlease head to the [post]({self.bot.planSQB.announcementMessage.jump_url}) and apply to be notified instead.")
			return
		channel = self.bot.get_channel(self.bot.channelIDs["announcements"])
		current_br = wtUtil.sqb_br(True)[0]
		self.bot.planSQB.timeframe = timeUtil.get_sqb_timebracket()
		embed = discord.Embed(title="SQB Plan", color=0xFF0000, description="A member has planned to play SQB today. You can apply to be notified once the session starts")
		embed.set_author(name=f"Posted by {interaction.user.name}", icon_url=interaction.user.display_avatar.url)
		embed.add_field(name="Starting", value=timeUtil.discord_timestamp(self.bot.planSQB.timeframe[0], "R"), inline=True)
		embed.add_field(name="Battle Rating", value=f"{current_br}", inline=True)
		embed.add_field(name="Ending", value=timeUtil.discord_timestamp(self.bot.planSQB.timeframe[1], "R"), inline=True)
		embed.add_field(name="Current applicants", value=f"{len(self.bot.planSQB.applicants)+1} / 8", inline=False)
		embed.set_image(url=(await genericUtil.random_propaganda(self.bot)))
		embed.set_footer(text=f"Provided by PECK bot", icon_url=self.bot.iconURL)
		async def apply(interaction:discord.Interaction):
			if self.bot.get_guild(self.bot.peckServer).get_role(self.bot.roleIDs["member"]) not in interaction.user.roles:
				await interaction.followup.send(content="You are not a member, you can not apply to join SQB.", ephemeral=True, silent=True)
			elif interaction.user.id not in self.bot.planSQB.applicants: 
				self.bot.planSQB.applicants.append(interaction.user.id)
				await interaction.followup.send(content="**You have been added to the applicants list! You will be notified by DM (or Pinged if DMs aren't accessible) when the session starts.**", ephemeral=True, silent=True)
				tmp = self.bot.planSQB.announcementMessage.embeds[0].set_field_at(
					index=3,
					name="Current applicants",
					value=f"{len(self.bot.planSQB.applicants)} / 8",
					inline=False
				)
				await self.bot.planSQB.announcementMessage.edit(embed=tmp)
			else:
				await interaction.followup.send(content="You have already applied before. *You will be notified once the session starts.*", ephemeral=True, silent=True)
		async def cancel(interaction:discord.Interaction):
			try:
				if interaction.user.id in self.bot.planSQB.applicants:
					self.bot.planSQB.applicants.remove(interaction.user.id)
					await interaction.followup.send(content="You have successfully been *removed* from the applicants list. **You will not be sent a DM when the session starts.**", ephemeral=True, silent=True)
					if len(self.bot.planSQB.applicants) < 1:
						await interaction.message.edit(content="All applicants have cancelled their application, so this plan has been cancelled.", embed=None, view=None)
						self.bot.planSQB = self.bot._PlanSQB()
					else:
						tmp = self.bot.planSQB.announcementMessage.embeds[0].set_field_at(
							index=3,
							name="Current applicants",
							value=f"{len(self.bot.planSQB.applicants)} / 8",
							inline=False
						)
						await self.bot.planSQB.announcementMessage.edit(embed=tmp)
				else:
					await interaction.followup.send(content="You haven't applied to the planned SQB session, *nothing has changed.*", ephemeral=True, silent=True)
			except:
				self.logger.exception("An error occurred while handling SQB plan application cancellation")
		self.bot.planSQB.announcementMessage = await channel.send(content=interaction.guild.get_role(self.bot.roleIDs["ping"]).mention, embed=embed, view=genericUtil.genericButtons(acceptFunc=apply, denyFunc=cancel, timeout=None, acceptLabel="Apply", denyLabel="Cancel Application"))
		if interaction.user.id not in self.bot.planSQB.applicants:
			self.bot.planSQB.applicants.append(interaction.user.id)
		embed = discord.Embed(title="SQB Plan", color=0x0000FF, timestamp=datetime.now(UTC), description="A member just called for SQB to be planned")
		embed.add_field(name="Username", value=f"{interaction.user.name}")
		embed.add_field(name="User ID", value=f"{interaction.user.id}")
		embed.add_field(name="User mention", value=interaction.user.mention)
		await (self.bot.get_channel(self.bot.channelIDs["spam"]).send(embed=embed))
		await interaction.edit_original_response(content=f"The SQB plan has been posted in announcements and you have been automatically added as an applicant. When you are ready to call for it, join <#{self.bot.channelIDs["sqb"]}>.\n-# if you join before the said timebracket the bot will check every ~30 seconds and announce once the timebracket starts")

	@discord.app_commands.command(name="recruit")
	@officer_only()
	@discord.app_commands.guild_only()
	@discord.app_commands.describe(username="Enter War Thunder username, who the message gets sent to",dc_user="Ping a user to send a message to")
	async def sqbrecruit(self, interaction: discord.Interaction, username:str|None=None, dc_user:discord.Member|None=None):
		"""[Officer] Recruits a given person for SQB"""
		await interaction.response.defer(thinking=True)
		identifier = None
		if username is not None:	identifier = username
		elif dc_user is not None:	identifier = dc_user.id
		else: return await interaction.edit_original_response(content="You must give me any user identifier.")
		if isinstance(identifier, int):
			user = self.bot.db.getByDID(identifier)
			user = user[0] if user else None
		elif isinstance(identifier, str):
			user = self.bot.db.getByName(identifier)
		else:
			raise ValueError(f"Invalid type given for identifier '{type(identifier)}'")
		if user is None:
			self.logger.debug(f"Could not find user '{identifier}' in the database")
			await interaction.edit_original_response(content=f"Could not find user '{identifier}' in the database")
			return
		current_br = wtUtil.sqb_br(True)
		message = f"Hello {user.username}!\nYou have been drafted for SQB.\nThe current BR is {current_br[0]}\n{await genericUtil.random_propaganda(self.bot)}"
		dc_user2 = self.bot.get_user(user.discord_id)
		async def accept(interaction2:discord.Interaction):
			await interaction2.followup.send(f"Splendid, please join <#{self.bot.channelIDs["sqb"]}> to start participating!")
			await interaction.edit_original_response(content=f"User '{interaction2.user.name}' has accepted participating.")
			return True
		async def deny(interaction2:discord.Interaction):
			await interaction2.followup.send("Understood.")
			await interaction.edit_original_response(content=f"User '{interaction2.user.name}' has denied participating this time.")
			return True
		try:
			await dc_user2.send(message, view=genericUtil.genericButtons(acceptFunc=accept, denyFunc=deny, acceptLabel="Accept Invitation", denyLabel="Deny Invitation", removeButtonsAfter=True))
			await interaction.edit_original_response(content=f"Message sent to {dc_user2.name} (War Thunder username: {user.username})")
		except discord.Forbidden:
			await interaction.edit_original_response(content=f"Could not send DM to {dc_user2.name} (War Thunder username: {user.username}).")

	@discord.app_commands.command(name="brackets")
	@officer_only()
	@discord.app_commands.guild_only()
	async def sqb_brackets(self, interaction:discord.Interaction):
		"""[Officer] Posts the seasonal schedule for SQB"""
		await interaction.response.defer(thinking=True)
		for i in (await interaction.channel.pins()):
			if i.content.startswith("1st week:") and i.author.id == self.bot.user.id:
				await i.delete()
				self.logger.debug("Pinned message found and deleted")
				break
		await interaction.edit_original_response(content=wtUtil.sqb_br())
		await (await interaction.original_response()).pin(reason="Pinning SQB BR message")
		def is_me(m:discord.Message):
			return m.author == interaction.client.user
		await interaction.channel.purge(limit=1, reason="Removing pin message", check=is_me)

	@discord.app_commands.command(name="current_br")
	async def current_br(self, interaction:discord.Interaction):
		"""[Public] Posts the current active BR"""
		await interaction.response.defer()
		tmp = wtUtil.sqb_br(True)
		if not tmp:
			await interaction.edit_original_response(content=f"Could not get current BR data. Please try again later...")
			self.logger.error(f"sqb_br returned '{tmp}'")
			return
		await interaction.edit_original_response(content=f"Current BR:\n# {tmp[0]}\nCurrent BR started {tmp[1][0]} and ends {tmp[1][1]}")

async def setup(bot:'Bot'):
	await bot.add_cog(SQBCog(bot))
if __name__ == "__main__":
	raise Exception("Start the program from the main process")